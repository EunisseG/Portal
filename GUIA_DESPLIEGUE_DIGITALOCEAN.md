# Guía de despliegue — DigitalOcean (Droplet Ubuntu)

Despliegue del Portal WORTEV en un **Droplet** de DigitalOcean con **Ubuntu 24.04 LTS**,
servido por **HTTPS con Let's Encrypt (Certbot)**.

En esta guía el dominio de ejemplo es **`portal.wortev.com`**: cámbialo por el tuyo en
todos los comandos.

Arquitectura:

```
Internet → nginx del host + Certbot (TLS 443) → 127.0.0.1:8080 → nginx del contenedor → PHP-FPM
```

El stack Docker sirve en `127.0.0.1:8080` (solo accesible desde el propio servidor). El
**nginx del sistema** termina el HTTPS y hace de proxy. Los ajustes de producción
(`docker-compose.prod.yml`) se activan desde el `.env`, sin editar `docker-compose.yml`.

> Lista completa de comprobaciones: **`CHECKLIST_PRODUCCION.md`**. Esta guía es el
> recorrido concreto para DigitalOcean.

---

## 0. Antes de empezar — datos que necesitas a mano

- [ ] Cuenta de DigitalOcean con un método de pago
- [ ] Acceso al **DNS** de tu dominio (para crear el registro del subdominio)
- [ ] Una **clave SSH** en tu PC (si no tienes: `ssh-keygen -t ed25519`; la pública queda en
      `~/.ssh/id_ed25519.pub`)
- [ ] Acceso al repositorio de GitHub (`https://github.com/EunisseG/Portal.git`). Si es
      privado, un **token personal** de GitHub o una clave de despliegue.
- [ ] El **`APP_KEY` actual** de tu `src/.env` de desarrollo, **si vas a llevar los datos
      actuales**. ⚠️ Sin el mismo `APP_KEY` no se pueden descifrar los datos guardados
      (tokens WHM, SMTP del panel, credenciales de integraciones).
- [ ] (Opcional) Un respaldo **reciente** de la base de datos. Genéralo en tu PC justo antes:
  ```bash
  docker compose exec -T mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' > backup_portal_AAAAMMDD.sql
  ```

---

## 1. Crear el Droplet

1. Panel de DigitalOcean → **Create → Droplets**.
2. **Región**: la más cercana a tus usuarios (para México, **SFO3** o **NYC3**).
3. **Imagen**: **Ubuntu 24.04 (LTS) x64**.
4. **Tamaño** (Basic, Regular SSD):

   | Plan | Para qué | Ajustes |
   |---|---|---|
   | 1 GB / 1 vCPU | ❌ No alcanza: MySQL se queda sin memoria | — |
   | **2 GB / 1 vCPU** | Mínimo razonable, pocos usuarios | swap 2 GB, `MYSQL_BUFFER_POOL=256M` |
   | **4 GB / 2 vCPU** | Recomendado | swap 2 GB, `MYSQL_BUFFER_POOL=512M` |

   Revisa los precios vigentes en el panel al crearlo. Puedes **subir de tamaño** después
   (Droplet → Resize) sin reinstalar.
5. **Autenticación**: **SSH Key** → agrega tu clave pública. Evita la opción de contraseña.
6. (Recomendado) **Backups** semanales del Droplet (cuestan un porcentaje extra del plan) y
   **Monitoring** (gratis: gráficas de CPU, memoria y disco, con alertas).
7. **Hostname**: p. ej. `portal-wortev`. Crea el Droplet y anota su **IP pública**.

---

## 2. Firewall (dos capas)

### 2a. Cloud Firewall de DigitalOcean
Panel → **Networking → Firewalls → Create Firewall**:
- **Inbound**: `SSH` (22), `HTTP` (80), `HTTPS` (443). Si puedes, limita el 22 a tu IP.
- **Outbound**: deja todo permitido (Composer/npm, MercadoPago, WhatsApp, SMTP, WHM).
- **Apply to Droplets**: selecciona tu Droplet.

### 2b. Firewall del sistema (`ufw`, ya conectado por SSH; ver paso 3)
```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ufw status
```

> MySQL (3306) y Redis (6379) **no** se abren: con `docker-compose.prod.yml` ni siquiera
> se publican fuera de la red interna de Docker.

---

## 3. Primer acceso y usuario de trabajo

Desde tu PC:
```bash
ssh root@LA_IP_PUBLICA
```

Actualiza el sistema y crea un usuario normal (`deploy`) para no trabajar como root:
```bash
apt update && apt -y upgrade
adduser deploy                 # pide una contraseña (la usará sudo)
usermod -aG sudo deploy
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy   # copia tu clave SSH
```

Cierra la sesión y entra ya como `deploy`:
```bash
ssh deploy@LA_IP_PUBLICA
id -u && id -g                 # normalmente 1000 y 1000: los usarás en el .env
```

> (Recomendado) Una vez que entres bien como `deploy`, desactiva el acceso de root por SSH:
> en `/etc/ssh/sshd_config` pon `PermitRootLogin no` y ejecuta `sudo systemctl restart ssh`.

---

## 4. Swap (imprescindible con 2 GB; útil con 4 GB)

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
echo 'vm.swappiness=10' | sudo tee /etc/sysctl.d/99-swappiness.conf && sudo sysctl --system
free -h                        # debe mostrar Swap: 2.0Gi
```

---

## 5. Instalar Docker + Docker Compose

```bash
sudo apt -y install ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

sudo usermod -aG docker deploy  # usar docker sin sudo
```
Cierra y vuelve a entrar por SSH para que el grupo `docker` tome efecto. Verifica:
```bash
docker version
docker compose version          # debe ser v2.24 o superior (los ajustes de producción lo requieren)
```

> Estar en el grupo `docker` equivale a tener permisos de administrador del servidor.
> Solo agrega a ese grupo usuarios de confianza.

---

## 6. Bajar el proyecto (git clone)

```bash
sudo apt -y install git
cd ~
git clone https://github.com/EunisseG/Portal.git portal-wortev
cd portal-wortev
```

> Si el repositorio es privado, GitHub pide autenticación: usa un **token personal**
> (GitHub → Settings → Developer settings → Personal access tokens) como contraseña, o
> configura una clave SSH de despliegue.

`vendor`, `node_modules`, `public/build` y `public/storage` **no** viajan en git: se generan
en el servidor en los pasos 8 y 9.

Si vas a restaurar tus datos, sube el respaldo desde tu PC (no está en git):
```bash
scp ./backup_portal_AAAAMMDD.sql deploy@LA_IP_PUBLICA:~/portal-wortev/
```

---

## 7. Configurar los `.env`

**`.env` de la raíz (Docker):**
```bash
cp .env.example .env
nano .env
```
Ajusta:
```ini
UID=1000                       # lo que dio `id -u`
GID=1000                       # lo que dio `id -g`
APP_PORT=8080                  # solo escucha en 127.0.0.1; el nginx del host hace de proxy
DB_PASSWORD=<contraseña-fuerte>
DB_ROOT_PASSWORD=<otra-contraseña-fuerte>

# Ajustes de producción: sin publicar MySQL/Redis, nginx solo local, sin mailpit
COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
MYSQL_BUFFER_POOL=256M         # 256M con 2 GB de RAM; 512M con 4 GB
```

> Con `COMPOSE_FILE` en el `.env`, **todos** los `docker compose ...` de esta guía usan
> automáticamente los ajustes de producción. Compruébalo con
> `docker compose config --services` (no debe aparecer `mailpit`).

**`src/.env` (Laravel):**
```bash
cp src/.env.example src/.env
nano src/.env
```
Ajusta lo esencial:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://portal.wortev.com
APP_KEY=                       # vacío si empiezas limpio (se genera en el paso 9);
                               # ⚠️ EL MISMO de desarrollo si restauras tus datos
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=portal_wortev
DB_USERNAME=wortev
DB_PASSWORD=<la misma que pusiste en el .env de la raíz>

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true     # cookies solo por HTTPS
REDIS_HOST=redis

MAIL_MAILER=smtp               # el SMTP real se configura en el panel; no uses mailpit

# Solo si empiezas limpio (crea el super administrador en el paso 10)
SUPERADMIN_NAME="Tu Nombre"
SUPERADMIN_EMAIL=admin@wortev.com
SUPERADMIN_PASSWORD=<contraseña-fuerte>
```

Protege ambos archivos:
```bash
chmod 600 .env src/.env
```

---

## 8. Levantar el stack

```bash
docker compose build
docker compose up -d
docker compose ps              # mysql, redis, app, nginx, queue y scheduler "Up"; sin mailpit
```

Comprueba que el worker procese **las dos colas** (`default` e `integrations`). Sin
`integrations` no salen los avisos de factura y comprobante ni los WhatsApp, y los pagos en
línea nunca se confirman:
```bash
docker inspect portal-wortev-queue --format '{{.Config.Cmd}}'
# debe incluir --queue=default,integrations
```

> Con 2 GB de RAM la primera compilación puede tardar varios minutos. Si `npm run build` o
> `composer install` se cortan por memoria, revisa que el swap esté activo (`free -h`).

---

## 9. Dependencias, clave y base de datos

```bash
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php artisan storage:link     # logo y favicon (public/storage no viaja en git)
docker compose exec app npm ci
docker compose exec app npm run build
```

Ahora elige **una** de las dos opciones:

### 9a. Empezar limpio
```bash
docker compose exec app php artisan key:generate     # ⚠️ respalda el APP_KEY que genere
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```
`db:seed` crea roles y permisos, el super administrador (con `SUPERADMIN_*`), las políticas
SLA, las áreas internas y las encuestas. En `APP_ENV=production` **no** siembra datos de
demostración y no duplica si se vuelve a correr.

### 9b. Restaurar tus datos actuales
Requiere el **mismo `APP_KEY`** que en desarrollo (paso 7).
```bash
docker compose exec -T mysql sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < backup_portal_AAAAMMDD.sql
docker compose exec app php artisan migrate --force  # aplica migraciones que el respaldo no tenga
docker compose exec app php artisan db:seed --force  # permisos y catálogos nuevos; no duplica
```

> Tras restaurar, borra el archivo del respaldo del servidor (`rm backup_portal_*.sql`):
> contiene todos tus datos.

### Al final, en ambos casos
```bash
docker compose exec app php artisan optimize         # cachés de config, rutas, vistas y eventos
docker compose restart app queue scheduler
curl -I http://127.0.0.1:8080/login                  # debe dar HTTP 200, no 502
```

---

## 10. DNS del subdominio

En el administrador DNS de tu dominio, crea un registro **A**:
- Nombre: `portal` → Valor: **IP pública** del Droplet.

> Si el dominio está en DigitalOcean: **Networking → Domains → tu dominio → A record**.

Espera a que propague (`ping portal.wortev.com` debe responder con la IP del Droplet).

---

## 11. nginx del host + HTTPS con Certbot

```bash
sudo apt -y install nginx certbot python3-certbot-nginx
```

Crea el sitio proxy: `sudo nano /etc/nginx/sites-available/portal-wortev`
```nginx
server {
    listen 80;
    server_name portal.wortev.com;

    # Algo mayor que el límite de subida de PHP (20 MB).
    client_max_body_size 25M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;   # exportaciones y PDFs pueden tardar
    }
}
```
Actívalo, quita el sitio de ejemplo y recarga:
```bash
sudo ln -s /etc/nginx/sites-available/portal-wortev /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Emite el certificado (Certbot agrega el 443 y la redirección HTTP→HTTPS):
```bash
sudo certbot --nginx -d portal.wortev.com --redirect
```
Comprueba la renovación automática:
```bash
sudo systemctl list-timers | grep certbot
sudo certbot renew --dry-run
```

> El proyecto ya confía en el proxy (`trustProxies` en `bootstrap/app.php`), así que
> Laravel genera enlaces `https://` correctos detrás de este nginx.

---

## 12. Configuración desde el panel

Entra a `https://portal.wortev.com/login` con el super administrador (o tus usuarios de
siempre si restauraste) y configura (detalle en `CHECKLIST_PRODUCCION.md` §10):

- [ ] **Sistema → Integraciones → SMTP**: correo real, y **Probar** que llegue
- [ ] **Sistema → Apariencia**: nombre, logo, favicon, **datos del emisor** y diseño de los
      **correos** (con vista previa)
- [ ] **Sistema → Modo de operación**: pagos manuales o automáticos
- [ ] **MercadoPago** (si los pagos son automáticos): credenciales, y en MercadoPago registra
      el webhook `https://portal.wortev.com/api/webhooks/mercadopago`
- [ ] **WhatsApp (Meta)**: credenciales y plantillas **aprobadas** con los nombres exactos
      `invoice_available` y `payment_proof_received` (más las que ya usaba el portal)
- [ ] **Ejecutivos de ventas** con correo y teléfono, ligados a sus servicios
- [ ] **WHM**: alta del servidor de hosting y **Probar conexión** (el Droplet debe alcanzar
      el WHM por el puerto 2087)

---

## 13. Verificación final

- [ ] `https://portal.wortev.com/login` carga con **candado** (TLS válido) y `http://` redirige a `https://`
- [ ] `docker compose ps`: mysql y redis **sin** `0.0.0.0:...`; nginx en `127.0.0.1:8080`; sin mailpit
- [ ] Desde tu PC, `http://LA_IP_PUBLICA:8080` **no** responde (solo se entra por HTTPS)
- [ ] Logo y favicon se ven (si no, falta `storage:link`)
- [ ] Correo de prueba del SMTP llega, con el diseño de la marca y el **logo visible**
- [ ] Cola: worker con `--queue=default,integrations`; `docker compose logs -f queue` muestra actividad
- [ ] Scheduler: `docker compose exec app php artisan schedule:list` muestra las tareas
- [ ] Pedido del cliente → orden de pago → recibo en PDF
- [ ] Comprobante → aviso al ejecutivo; aprobación → orden pagada y factura generada
- [ ] Factura subida → aviso al cliente
- [ ] Gráficas, exportaciones y búsqueda funcionan
- [ ] `free -h` con el portal en uso: queda memoria libre o usa poco swap. Si el swap se usa
      mucho todo el tiempo, sube el Droplet a 4 GB (Droplet → Resize).

---

## 14. Respaldos y monitoreo

- [ ] **Backups del Droplet** activados (paso 1): copia completa semanal de la máquina
- [ ] **Respaldo diario de la base de datos fuera del servidor**. Ejemplo con cron (como `deploy`):
  ```bash
  mkdir -p ~/respaldos
  crontab -e
  # Todos los días a las 02:30, conserva 14 días:
  30 2 * * * cd ~/portal-wortev && docker compose exec -T mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' | gzip > ~/respaldos/portal_$(date +\%F).sql.gz && find ~/respaldos -name 'portal_*.sql.gz' -mtime +14 -delete
  ```
  Después copia `~/respaldos` a otro lugar (p. ej. **DigitalOcean Spaces** con `s3cmd`/`rclone`,
  o descárgalo a tu PC). Un respaldo que solo vive en el mismo Droplet no protege si el
  Droplet se pierde.
- [ ] Los 3 respaldos de la **actualización de dependencias** (`src/storage/app/dependency-backups`)
      no sustituyen a este respaldo: viven en el mismo disco
- [ ] Respaldo de `src/.env` y del **`APP_KEY`** en un lugar seguro, fuera del servidor
- [ ] **Monitoring** de DigitalOcean con alertas de memoria (> 90 %) y disco (> 80 %)
- [ ] Rotación de logs de `src/storage/logs` (que no crezcan sin límite)

---

## 15. Actualizaciones futuras (re-deploy)

```bash
cd ~/portal-wortev
git pull
docker compose build app                              # si cambió el Dockerfile
docker compose up -d                                  # aplica cambios de docker-compose*.yml
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app npm ci && docker compose exec app npm run build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force   # permisos y datos base nuevos; no duplica
docker compose exec app php artisan storage:link      # si ya existe, solo avisa
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
docker compose exec app php artisan queue:restart
docker compose restart app queue scheduler
```

> ⚠️ Si actualizaste dependencias **desde el panel** (Seguridad → Dependencias /
> Seguridad), el servidor tiene `src/composer.lock` y `src/package-lock.json` distintos a
> GitHub. Llévalos al repositorio **antes** del `git pull`, o el despliegue deshará la
> actualización. Emergencia si la app no responde:
> ```bash
> docker compose exec app php artisan dependencies:restore <id>             # código
> docker compose exec app php artisan dependencies:restore <id> --database  # base de datos (destructivo)
> docker compose exec app php artisan up                                    # salir de mantenimiento
> ```

---

### Diagnóstico rápido

```bash
docker compose ps                          # estado de contenedores
docker compose logs -f app                 # errores de la app
docker compose logs -f queue               # avisos, webhooks y actualizaciones de dependencias
docker compose logs --tail=50 nginx        # útil ante un 502
sudo tail -n 50 /var/log/nginx/error.log   # nginx del host (proxy/HTTPS)
free -h && df -h                           # memoria, swap y disco
docker compose exec app php artisan about  # resumen de entorno y config
```

| Síntoma | Causa habitual |
|---|---|
| El sitio no abre | Firewall (Cloud Firewall o `ufw`) sin 80/443, o DNS sin propagar |
| `502 Bad Gateway` | El stack no está arriba (`docker compose ps`) o `APP_PORT` distinto al del proxy |
| No se ven logo ni favicon | Falta `php artisan storage:link` |
| No llegan avisos ni se confirman pagos | El worker no procesa la cola `integrations` |
| La app se cae sola de vez en cuando | Falta de memoria: revisa `free -h`, swap y `MYSQL_BUFFER_POOL` |
| Enlaces de correo con `http://` o `localhost` | `APP_URL` incorrecto; luego `php artisan optimize` |
| No sale ningún correo / `504` al registrarse | DigitalOcean **bloquea el SMTP saliente** (puertos 25, 465, 587) en cuentas nuevas. Prueba el puerto (abajo) y usa el **2525** de tu proveedor o pide el desbloqueo a soporte |

### Probar si el Droplet puede llegar a tu SMTP

```bash
# Desde el Droplet (cambia el host por el de tu proveedor de correo):
for p in 25 465 587 2525; do timeout 5 bash -c "</dev/tcp/smtp.tu-proveedor.com/$p" \
  && echo "puerto $p ABIERTO" || echo "puerto $p BLOQUEADO"; done
```

- Si **587/465 salen bloqueados** y el **2525** abierto: cambia el puerto del SMTP a `2525` en
  **Sistema → Integraciones → SMTP** (lo ofrecen SendGrid, Mailgun, Brevo y otros).
- Si todos salen bloqueados: abre un ticket a soporte de DigitalOcean pidiendo habilitar el
  SMTP saliente, o usa un proveedor con API HTTP.
- Después prueba con el botón **Probar** del panel SMTP y revisa
  `docker compose logs -f queue` (la verificación de cuenta sale por la cola).
