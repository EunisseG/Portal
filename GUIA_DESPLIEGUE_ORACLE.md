# Guía de despliegue — Staging en Oracle Cloud (ARM + AlmaLinux)

Despliegue del Portal WORTEV en una instancia **Always Free ARM (Ampere A1)** de
Oracle Cloud con **AlmaLinux**, servido en **https://staging.wortev.com** con
**Let's Encrypt (Certbot)** y los datos actuales restaurados desde el respaldo.

Arquitectura elegida (simple y robusta para staging):

```
Internet → (nginx del host + Certbot, TLS 443) → localhost:8080 → nginx del contenedor → PHP-FPM
```

El stack Docker sigue sirviendo en el puerto `8080` del host (HTTP interno). El
**nginx del sistema** termina el HTTPS y hace de proxy inverso. Así no tocamos el
`docker-compose.yml`.

> Notas de arquitectura ARM: el stack usa imágenes multiarquitectura
> (php:8.3-fpm, mysql:8, redis:7, nginx) que tienen build ARM64, así que
> funciona en Ampere A1 sin cambios.

---

## 0. Antes de empezar — datos que necesitas a mano

- [ ] Cuenta Oracle Cloud creada
- [ ] Dominio `wortev.com` con acceso a su **DNS** (para crear el registro `staging`)
- [ ] El archivo de respaldo **`backup_portal_20261014.sql`** (en la raíz del proyecto)
- [ ] El **`APP_KEY` actual** del proyecto (de tu `src/.env` de desarrollo).
      ⚠️ **Imprescindible** para que los datos cifrados (tokens WHM, SMTP del panel)
      sigan funcionando. Cópialo antes de empezar.

---

## 1. Crear la instancia en Oracle Cloud

1. Consola de Oracle → **Compute → Instances → Create instance**.
2. **Image & shape**:
   - Image: **AlmaLinux** (9.x). Si no aparece en "Platform images", búscala en
     "Marketplace" / "Partner images".
   - Shape: **Ampere (VM.Standard.A1.Flex)**. Asigna, p. ej., **2 OCPU / 12 GB**
     (dentro de la capa Always Free).
3. **Networking**: deja que cree una VCN/subred pública. Anota la **IP pública**.
4. **SSH keys**: sube tu clave pública (o deja que genere un par y **descarga la
   privada**). La usarás para entrar.
5. Crea la instancia y espera a que esté **Running**.

---

## 2. Abrir puertos (¡el paso que todos olvidan en Oracle!)

Oracle bloquea todo por defecto en **dos** capas. Hay que abrir **80 y 443** en ambas.

### 2a. Security List (consola de Oracle)
1. Consola → **Networking → Virtual Cloud Networks → tu VCN → Security Lists →
   Default Security List**.
2. **Add Ingress Rules** (una por puerto):
   - Source CIDR `0.0.0.0/0`, IP Protocol **TCP**, Destination Port **80**
   - Source CIDR `0.0.0.0/0`, IP Protocol **TCP**, Destination Port **443**
   - (El 22/SSH suele venir abierto; si no, ábrelo también.)

### 2b. Firewall del SO (en la instancia, ya conectado por SSH — ver paso 3)
```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

> Si tras todo esto el sitio no responde, el 99% de las veces es un puerto sin
> abrir en una de las dos capas.

---

## 3. Primer acceso y preparación del sistema

Desde tu PC (PowerShell o terminal):
```bash
ssh -i ruta/a/tu-clave-privada opc@LA_IP_PUBLICA
```
> En las imágenes de Oracle el usuario suele ser **`opc`** (no root). Usa `sudo`.

Actualiza el sistema:
```bash
sudo dnf update -y
```

---

## 4. Instalar Docker + Docker Compose en AlmaLinux

```bash
# Repo oficial de Docker para RHEL/AlmaLinux
sudo dnf -y install dnf-plugins-core
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo

# Docker Engine + Compose plugin
sudo dnf -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Arrancar y habilitar al inicio
sudo systemctl enable --now docker

# Permitir usar docker sin sudo (cierra y reabre la sesión SSH tras esto)
sudo usermod -aG docker opc
```
Cierra y vuelve a entrar por SSH para que el grupo `docker` tome efecto. Verifica:
```bash
docker version
docker compose version
```

---

## 5. Subir el proyecto (rsync desde tu PC)

El proyecto no está en git, así que lo subimos con **rsync** excluyendo lo que se
regenera en el servidor (`vendor`, `node_modules`, caches). Desde tu PC
(idealmente desde WSL/Git Bash; en Windows puro usa `scp -r` o WinSCP):

```bash
# Desde la carpeta que contiene el proyecto, en tu PC:
rsync -avz --progress \
  --exclude 'src/vendor' \
  --exclude 'src/node_modules' \
  --exclude 'src/storage/framework/cache/*' \
  --exclude 'src/storage/logs/*' \
  --exclude '.git' \
  -e "ssh -i ruta/a/tu-clave-privada" \
  ./portal-wortev/  opc@LA_IP_PUBLICA:/home/opc/portal-wortev/
```

Si no tienes rsync en Windows, alternativa con scp (sube todo; más lento):
```bash
scp -i ruta/a/tu-clave-privada -r ./portal-wortev opc@LA_IP_PUBLICA:/home/opc/
```

También sube el respaldo de la BD (si no quedó incluido):
```bash
scp -i ruta/a/tu-clave-privada ./portal-wortev/backup_portal_20261014.sql \
  opc@LA_IP_PUBLICA:/home/opc/portal-wortev/
```

---

## 6. Configurar los `.env` en el servidor

Conéctate por SSH y entra al proyecto:
```bash
cd /home/opc/portal-wortev
```

**`.env` de la raíz (Docker):**
```bash
cp .env.example .env
nano .env
```
Ajusta:
```ini
APP_PORT=8080          # el stack sirve aquí; el nginx del host hará proxy
DB_PASSWORD=<contraseña-fuerte>
DB_ROOT_PASSWORD=<otra-contraseña-fuerte>
UID=1000
GID=1000
```

**`src/.env` (Laravel):**
```bash
cp src/.env.example src/.env
nano src/.env
```
Ajusta lo esencial:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://staging.wortev.com
APP_KEY=base64:...   # ⚠️ EL MISMO que usas en desarrollo (datos cifrados)

DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=portal_wortev
DB_USERNAME=wortev
DB_PASSWORD=<la misma que pusiste en el .env raíz>

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true     # cookies solo por HTTPS
REDIS_HOST=redis

MAIL_MAILER=smtp               # el SMTP real viaja en la BD (panel); no uses mailpit
```

---

## 7. Levantar el stack

```bash
docker compose build
docker compose up -d
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app npm ci
docker compose exec app npm run build
```

> El build en ARM puede tardar algo más la primera vez. Si algo falla por
> arquitectura, avísame con el error exacto.

---

## 8. Restaurar la base de datos (tus datos actuales)

```bash
docker compose exec -T mysql sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < backup_portal_20261014.sql
```

Luego aplica migraciones pendientes (por si el código trae nuevas) y cachea:
```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan event:cache
docker compose exec app php artisan view:cache
```

> Si prefieres empezar limpio (sin restaurar), omite el restore y en su lugar
> corre los seeders de roles y super admin (ver `DEPLOYMENT.md` §3 y §5).

Verifica que el stack responde en local del servidor:
```bash
curl -I http://localhost:8080/login     # debe dar HTTP 200/302, no 502
```

---

## 9. DNS del subdominio

En el administrador DNS de `wortev.com`, crea:
- Registro **A**: `staging` → **IP pública** de la instancia Oracle.

Espera a que propague (`ping staging.wortev.com` debe resolver a esa IP).

---

## 10. nginx del host + HTTPS con Certbot

Instala nginx y certbot en AlmaLinux:
```bash
sudo dnf -y install nginx
sudo systemctl enable --now nginx
sudo dnf -y install certbot python3-certbot-nginx
```

Crea el sitio proxy. `sudo nano /etc/nginx/conf.d/staging.conf`:
```nginx
server {
    listen 80;
    server_name staging.wortev.com;

    client_max_body_size 25M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```
Valida y recarga:
```bash
sudo nginx -t && sudo systemctl reload nginx
```

Emite el certificado (Certbot edita el nginx y añade el 443 solo):
```bash
sudo certbot --nginx -d staging.wortev.com
```
- Responde el correo y acepta los términos.
- Elige **redirigir HTTP→HTTPS** cuando lo pregunte.

Certbot ya deja la **renovación automática** (timer de systemd). Compruébalo:
```bash
sudo systemctl list-timers | grep certbot
sudo certbot renew --dry-run
```

> El proyecto ya confía en el proxy (se configuró `trustProxies` en
> `bootstrap/app.php`), así que Laravel generará enlaces `https://` correctos
> (verificación de correo, etc.) detrás de este nginx.

---

## 11. Crear/validar el acceso admin

Si **restauraste** la BD, tus usuarios admin ya existen (entra con los de siempre).

Si empezaste **limpio**, crea el super admin (define las variables en `src/.env`):
```ini
SUPERADMIN_NAME="Tu Nombre"
SUPERADMIN_EMAIL=admin@wortev.com
SUPERADMIN_PASSWORD=<contraseña-fuerte>
```
```bash
docker compose exec app php artisan db:seed --class="Database\\Seeders\\RolesAndPermissionsSeeder" --force
docker compose exec app php artisan db:seed --class="Database\\Seeders\\InitialUsersSeeder" --force
```

---

## 12. Verificación final

- [ ] `https://staging.wortev.com/login` carga con **candado** (TLS válido)
- [ ] Login correcto en el panel
- [ ] Enlaces de correo salen como `https://staging.wortev.com/...` (no http ni localhost)
- [ ] Prueba de SMTP desde el panel → llega el correo
- [ ] Cola y scheduler corriendo: `docker compose ps` (queue y scheduler "Up")
- [ ] Gráficas, export, búsqueda funcionan
- [ ] WHM: dar de alta un servidor de prueba y "probar conexión" (requiere que la
      instancia alcance el WHM por 2087; si está en otra red, abre salida)

---

## 13. Endurecer el staging (recomendado aunque sea de prueba)

- [ ] En el `docker-compose.yml`, **no publicar** `mysql` (3306) ni `redis` (6379)
      hacia fuera. Para staging, como el nginx del host entra por `127.0.0.1:8080`,
      puedes incluso cambiar el publish de nginx a `127.0.0.1:8080:80` para que el
      stack no sea accesible directo desde internet (solo vía el proxy TLS).
- [ ] `mailpit` no expuesto públicamente (o quítalo del compose en el servidor).
- [ ] Contraseñas fuertes en ambos `.env`.
- [ ] Respaldar `src/.env` + `APP_KEY` fuera del servidor.

---

## 14. Actualizaciones futuras (re-deploy)

Mientras no uses git, cada actualización es: volver a hacer **rsync** del código y
luego:
```bash
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app npm ci && docker compose exec app npm run build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
docker compose exec app php artisan event:cache
docker compose exec app php artisan queue:restart
docker compose restart app
```

> Recomendación: cuando puedas, crea un repositorio **git privado** (GitHub/GitLab
> gratis). Convierte estas actualizaciones en un simple `git pull` y evita el
> rsync manual. Te puedo ayudar a configurarlo.
