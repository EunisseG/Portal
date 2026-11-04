# Checklist completo — Paso a producción (Portal WORTEV)

Guía integral para pasar el portal de desarrollo a un servidor de producción.
Marca cada punto. Los comandos asumen que corres desde la raíz del proyecto
(donde está `docker-compose.yml`) salvo que se indique otra cosa.

> Hay **dos** archivos `.env`:
> - **Raíz** (`.env`): variables del entorno Docker (puertos, UID/GID, credenciales de MySQL del contenedor).
> - **App** (`src/.env`): configuración de Laravel (APP_*, DB_*, MAIL_*, etc.).

---

## 0. Pre-requisitos del servidor

- [ ] Servidor Linux con **Docker** y **Docker Compose** instalados
- [ ] Recursos adecuados (mín. recomendado: 2 vCPU / 4 GB RAM; MySQL + Redis + PHP)
- [ ] **DNS**: dominio apuntando a la IP del servidor (registro A / AAAA)
- [ ] **TLS/HTTPS** disponible (certificado para el dominio; ver sección 7)
- [ ] Acceso de red saliente al **WHM** de cada servidor de hosting (puerto `2087/tcp`)
- [ ] Reloj del servidor sincronizado (NTP) — importante para tokens, 2FA y expiraciones
- [ ] Repositorio clonado en el servidor

---

## 1. Variables de entorno Docker (`.env` en la raíz)

- [ ] Copiar `.env.example` → `.env`
- [ ] `UID`/`GID` acordes al usuario del servidor (en Linux: `id -u` / `id -g`)
- [ ] `APP_PORT`: puerto del host que servirá nginx (p. ej. `8080`; detrás de un proxy/HTTPS)
- [ ] `DB_PASSWORD` y `DB_ROOT_PASSWORD`: **contraseñas fuertes** (no las de ejemplo)
- [ ] **No** publicar `DB_PORT` ni `REDIS_PORT` a internet (ver sección 7)

---

## 2. Configuración de Laravel (`src/.env`)

- [ ] Copiar `src/.env.example` → `src/.env`
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`  ⚠️ (en `true` es lento y expone errores/datos)
- [ ] `APP_URL=https://portal.tu-dominio.com` (afecta enlaces de correos: verificación, reset de contraseña)
- [ ] `LOG_LEVEL=warning` (o `error`) en vez de `debug`
- [ ] `BCRYPT_ROUNDS=12` (o más) correcto
- [ ] `DB_*` apuntando a la BD de producción, con las credenciales fuertes de la sección 1
- [ ] `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`
- [ ] `SESSION_DOMAIN` y `SESSION_SECURE_COOKIE=true` (cookies solo por HTTPS)
- [ ] `APP_LOCALE` según preferencia (p. ej. `es`)
- [ ] **`APP_KEY`**: generar **una sola vez** con `php artisan key:generate` y **respaldarla**
  ⚠️ Si cambia, se pierden los datos cifrados (tokens WHM, contraseñas SMTP guardadas)

### Correo (crítico para verificación de cuenta)
- [ ] `MAIL_MAILER=smtp` (**no** apuntar a mailpit en producción)
- [ ] El SMTP real se configura **desde el panel** (Sistema → Configuración → SMTP); el
      provider lo aplica en runtime. Verifica que `MAIL_MAILER` y el SMTP del panel sean coherentes.
- [ ] Probar envío real (botón "Probar" en el panel SMTP) y revisar que llegue
- [ ] Confirmar que `APP_URL` es la de producción para que los enlaces de los correos sean válidos

---

## 3. Construcción y primer arranque

- [ ] `docker compose build`
- [ ] `docker compose up -d`
- [ ] `docker compose exec app composer install --no-dev --optimize-autoloader`
- [ ] `docker compose exec app php artisan key:generate` (solo la primera vez; respaldar APP_KEY)
- [ ] `docker compose exec app php artisan migrate --force`
- [ ] `docker compose exec app php artisan db:seed --class="Database\\Seeders\\RolesAndPermissionsSeeder" --force`
- [ ] Definir `SUPERADMIN_NAME/EMAIL/PASSWORD` en `src/.env`
- [ ] `docker compose exec app php artisan db:seed --class="Database\\Seeders\\InitialUsersSeeder" --force`
- [ ] `docker compose exec app npm ci`
- [ ] `docker compose exec app npm run build`

---

## 4. Rendimiento (cachés + OPcache)

> Reconstruir las cachés en **cada** despliegue. En producción el portal es mucho
> más rápido con esto activado (en desarrollo se deja apagado a propósito).

- [ ] `docker compose exec app php artisan config:cache`
- [ ] `docker compose exec app php artisan route:cache`
- [ ] `docker compose exec app php artisan event:cache`
- [ ] `docker compose exec app php artisan view:cache`
- [ ] **OPcache** (ya habilitado en la imagen vía `docker/php/php.ini`). Para producción
      afinar `opcache.validate_timestamps=0` (más rápido) y recordar que **cada deploy**
      requiere `docker compose restart app` para que PHP tome el código nuevo.
- [ ] Verificar OPcache activo: `docker compose exec app php -i | grep opcache.enable` → `On`

> Si algo "no toma efecto" tras un cambio con cachés activas:
> `docker compose exec app php artisan optimize:clear` y volver a cachear.

---

## 5. Procesos en segundo plano

- [ ] Contenedor **`queue`** corriendo (`docker compose ps` → Up)
- [ ] Contenedor **`scheduler`** corriendo (recordatorios de vencimiento, tareas programadas)
- [ ] Tras cada deploy: `docker compose exec app php artisan queue:restart` para que los
      workers tomen el código nuevo
- [ ] `restart: unless-stopped` en `queue`/`scheduler` (ya configurado) para que sobrevivan reinicios

---

## 6. Red / nginx

- [ ] Al recrear el contenedor `app`, reiniciar nginx también (o usar `docker compose up -d`
      sin `--no-deps`). La config de nginx ya re-resuelve la IP de `app` en runtime
      (resolver `127.0.0.11`), lo que evita el 502 por IP cacheada.
- [ ] `client_max_body_size` de nginx coherente con `upload_max_filesize`/`post_max_size` de PHP
      (hoy ambos ~20 MB)

---

## 7. Seguridad / endurecimiento

- [ ] **HTTPS obligatorio**: proxy/terminador TLS delante de nginx, con redirección 80→443
- [ ] `SESSION_SECURE_COOKIE=true` y `APP_URL` en `https://`
- [ ] Puertos de **MySQL** (3306) y **Redis** (6379) **no** publicados a internet
      (quitar los `ports:` en producción o restringir por firewall)
- [ ] **mailpit** retirado o no expuesto públicamente (es solo para pruebas)
- [ ] `.env` (raíz y `src/`) fuera del control de versiones y con permisos restringidos
- [ ] `APP_KEY` respaldada en un gestor de secretos (no solo en el `.env`)
- [ ] Firewall del servidor: exponer solo 80/443 (y SSH restringido)
- [ ] Revisar que `/.env`, `/.git` no son accesibles por web (nginx ya deniega archivos ocultos)
- [ ] Redis con contraseña si queda accesible en red (`REDIS_PASSWORD`)

---

## 8. Acceso inicial y roles

- [ ] Super administrador creado por `InitialUsersSeeder` (datos del `.env`)
- [ ] Inicio de sesión correcto en el área admin
- [ ] (Opcional) Retirar `SUPERADMIN_PASSWORD` del `.env` tras crear el usuario
- [ ] Alta de admins/staff desde **Sistema → Usuarios**
- [ ] Revisar roles/permisos en **Seguridad → Roles y permisos**
- [ ] Probar un alta de **cliente** y confirmar que el rol es `client` (y que llega el correo)

---

## 9. Integración de hosting (WHM/cPanel)

- [ ] API Token creado en WHM (*WHM → Development → Manage API Tokens*)
- [ ] Servidor dado de alta (**Sistema → Servidores → Nuevo**): host, puerto `2087`, usuario `root`, token
- [ ] Opción de verificación TLS acorde al certificado del WHM
- [ ] **Guardar y probar conexión** → resultado correcto
- [ ] Vincular/crear una cuenta cPanel de prueba y verificar listado de correos
- [ ] Revisar **Integraciones → Logs** (eventos `whm.*`) sin errores

---

## 10. Verificación funcional final

- [ ] Correo real probado (verificación de registro / recuperación de contraseña) → **llega**
- [ ] Pago en línea de prueba (si aplica) y conciliación por webhook
- [ ] Cola procesando (disparar una notificación y verificar que se envía)
- [ ] Scheduler activo (recordatorios de vencimiento)
- [ ] Pantalla de **Gráficas** carga y exporta (Excel/CSV)
- [ ] Navegación entre secciones fluida (SPA con `wire:navigate`)
- [ ] 2FA: alta/activación/desactivación funcionan

---

## 11. Respaldos y monitoreo

- [ ] **Respaldo de base de datos** programado (dump periódico del volumen `mysql-data`)
- [ ] Respaldo seguro de `src/.env` + `APP_KEY`
- [ ] Rotación de logs (`storage/logs`) para que no crezcan sin límite
- [ ] Monitoreo básico: salud de contenedores, espacio en disco, uso de memoria
- [ ] Plan de restauración probado al menos una vez (restaurar un dump en un entorno aparte)

---

## 12. Rutina de despliegues posteriores

Para cada actualización de código en producción:

```bash
git pull
docker compose build app                       # si cambió el Dockerfile o deps del sistema
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app npm ci && docker compose exec app npm run build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize    # config + route + view cache
docker compose exec app php artisan event:cache
docker compose exec app php artisan queue:restart
docker compose up -d                            # recrea lo necesario (reinicia nginx junto a app)
```

> Si usas `opcache.validate_timestamps=0`, añade `docker compose restart app`
> para que PHP cargue el código nuevo.

---

### Comandos de diagnóstico útiles

```bash
docker compose ps                               # estado de contenedores
docker compose logs -f app                      # logs de la app
docker compose logs --tail=50 nginx             # logs de nginx (útil ante 502)
docker compose exec app php artisan about       # resumen de entorno/config
docker compose exec app php -i | grep opcache   # estado de OPcache
docker compose exec app php artisan optimize:clear   # limpia todas las cachés
```
