# Checklist completo — Paso a producción (Portal WORTEV)

Guía integral para pasar el portal de desarrollo a un servidor de producción.
Marca cada punto. Los comandos asumen que corres desde la raíz del proyecto
(donde está `docker-compose.yml`) salvo que se indique otra cosa.

> Hay **dos** archivos `.env`:
> - **Raíz** (`.env`): variables del entorno Docker (puertos, UID/GID, credenciales de MySQL del contenedor).
> - **App** (`src/.env`): configuración de Laravel (APP_*, DB_*, MAIL_*, etc.).

> **Tres pasos que no se pueden omitir** (sin ellos, partes del portal fallan en silencio):
> 1. El worker de cola debe procesar `default` **e** `integrations` (ya viene así en
>    `docker-compose.yml`). Sin `integrations` no salen avisos de factura/comprobante,
>    WhatsApp, Slack ni alertas de SLA, y **los pagos en línea nunca se confirman**
>    (el webhook de MercadoPago se procesa en esa cola).
> 2. `php artisan storage:link` — sin él no se ven el logo ni el favicon (ni en el portal
>    ni en los correos).
> 3. `php artisan db:seed --force` — crea roles, super admin, políticas SLA, áreas
>    internas y encuestas.

---

## 0. Pre-requisitos del servidor

- [ ] Servidor Linux con **Docker** y **Docker Compose** instalados
- [ ] Recursos: **mínimo 2 GB de RAM + 2 GB de swap** (pocos usuarios);
      **recomendado 2 vCPU / 4 GB RAM**. Con 1 GB o menos MySQL se queda sin memoria
      y la app se cae de forma intermitente. `composer`, `npm run build` y la
      actualización de dependencias desde el panel necesitan más de 1 GB.
- [ ] **Swap** activo (ver sección 4b)
- [ ] **Disco** suficiente: la actualización de dependencias guarda **3 respaldos**
      completos (base de datos + `vendor` + assets) en el mismo servidor; deja margen
- [ ] **DNS**: dominio apuntando a la IP del servidor (registro A / AAAA)
- [ ] **TLS/HTTPS** disponible (certificado para el dominio; ver sección 7)
- [ ] Acceso de red saliente al **WHM** de cada servidor de hosting (puerto `2087/tcp`)
- [ ] Acceso de red saliente a internet (Composer/npm, MercadoPago, Meta/WhatsApp, SMTP)
- [ ] Reloj del servidor sincronizado (NTP) — importante para tokens, 2FA y expiraciones
- [ ] Repositorio clonado en el servidor:
      `git clone https://github.com/EunisseG/Portal.git portal-wortev`

> Recorrido concreto en DigitalOcean (Droplet, firewall, swap, Docker, HTTPS):
> **`GUIA_DESPLIEGUE_DIGITALOCEAN.md`**.

> Rendimiento: en un servidor Linux el portal responde mucho más rápido que en
> Docker Desktop sobre Windows (allí la carpeta compartida con Windows es el cuello
> de botella). Las tareas pesadas (compilar, exportar, PDF) dependen de la CPU.

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
- [ ] `APP_URL=https://portal.tu-dominio.com` (afecta enlaces de correos, el logo de los
      correos y la URL del webhook de pagos)
- [ ] `LOG_LEVEL=warning` (o `error`) en vez de `debug`
- [ ] `BCRYPT_ROUNDS=12` (o más) correcto
- [ ] `DB_*` apuntando a la BD de producción, con las credenciales fuertes de la sección 1
- [ ] `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`
- [ ] `SESSION_DOMAIN` y `SESSION_SECURE_COOKIE=true` (cookies solo por HTTPS)
- [ ] `APP_LOCALE` según preferencia (p. ej. `es`)
- [ ] **`APP_KEY`**: generar **una sola vez** con `php artisan key:generate` y **respaldarla**
  ⚠️ Si cambia, se pierden los datos cifrados (tokens WHM, contraseñas SMTP guardadas,
  credenciales de integraciones)
- [ ] (Opcional) `DEPENDENCY_BACKUP_PATH` / `DEPENDENCY_KEEP_BACKUPS`: carpeta y número de
      respaldos de la actualización de dependencias (por defecto
      `storage/app/dependency-backups` y **3**)

### Correo (crítico para verificación de cuenta)
- [ ] `MAIL_MAILER=smtp` (**no** apuntar a mailpit en producción)
- [ ] El SMTP real se configura **desde el panel** (Sistema → Integraciones → SMTP →
      Configurar); el portal lo aplica en runtime. Verifica que `MAIL_MAILER` y el SMTP del
      panel sean coherentes.
- [ ] Probar envío real (botón "Probar" en el panel SMTP) y revisar que llegue
- [ ] Confirmar que `APP_URL` es la de producción para que los enlaces de los correos sean válidos

---

## 3. Construcción y primer arranque

- [ ] Definir `SUPERADMIN_NAME/EMAIL/PASSWORD` en `src/.env` (los usa el seeder)
- [ ] `docker compose build`
- [ ] `docker compose up -d`
- [ ] `docker compose exec app composer install --no-dev --optimize-autoloader`
- [ ] `docker compose exec app php artisan key:generate` (solo la primera vez; respaldar APP_KEY)
- [ ] `docker compose exec app php artisan migrate --force`
- [ ] `docker compose exec app php artisan db:seed --force`
      Crea roles y permisos, el super admin, las políticas SLA, las áreas internas y las
      encuestas. En `APP_ENV=production` **no** siembra datos de demostración. Es seguro
      volver a correrlo: no duplica.
- [ ] `docker compose exec app php artisan storage:link`
      (`public/storage` no viaja en git; sin este enlace no se ven el logo ni el favicon)
- [ ] `docker compose exec app npm ci`
- [ ] `docker compose exec app npm run build`

---

## 4. Rendimiento (cachés + OPcache)

> Reconstruir las cachés en **cada** despliegue. En producción el portal es mucho
> más rápido con esto activado (en desarrollo se deja apagado a propósito).

- [ ] `docker compose exec app php artisan optimize` (config + rutas + vistas + eventos)
- [ ] **OPcache** (ya habilitado en la imagen vía `docker/php/php.ini`).
      ⚠️ **No** pongas `opcache.validate_timestamps=0`: la actualización de dependencias
      desde el panel reemplaza `vendor` sin reiniciar PHP-FPM, y con ese ajuste PHP
      seguiría ejecutando el código viejo mezclado con el nuevo. Déjalo como viene.
- [ ] Verificar OPcache activo: `docker compose exec app php -i | grep opcache.enable` → `On`

> Si algo "no toma efecto" tras un cambio con cachés activas:
> `docker compose exec app php artisan optimize:clear` y volver a cachear.

### 4b. Memoria (servidores pequeños)

- [ ] **Swap** en el host (si el servidor tiene 4 GB o menos), p. ej. 2 GB:
  ```bash
  sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
  sudo mkswap /swapfile && sudo swapon /swapfile
  echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
  ```
- [ ] Usar los **ajustes de producción** `docker-compose.prod.yml`: en el `.env` de la raíz
      del servidor pon `COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml`. Con eso
      MySQL y Redis no se publican, nginx escucha solo en `127.0.0.1`, **mailpit no
      arranca** y MySQL limita su memoria.
- [ ] **Memoria de MySQL**: `MYSQL_BUFFER_POOL=256M` (servidor de 2 GB) o `512M` (4 GB) en el
      mismo `.env`. Aplicar: `docker compose up -d mysql`.

---

## 5. Procesos en segundo plano

- [ ] Contenedor **`queue`** corriendo (`docker compose ps` → Up)
- [ ] El worker procesa **las dos colas**: su comando en `docker-compose.yml` debe incluir
      `--queue=default,integrations`. Compruébalo con
      `docker inspect portal-wortev-queue --format '{{.Config.Cmd}}'`.
      En `integrations` viajan: avisos de factura y comprobante, WhatsApp, Slack, alertas
      de SLA, avisos de mantenimiento, auditoría de dependencias y **el webhook de
      MercadoPago** (sin esa cola los pagos en línea nunca se confirman).
- [ ] Contenedor **`scheduler`** corriendo. Usa `php artisan schedule:work` (no un bucle
      con `sleep`), así no se salta tareas a hora exacta. Programa: recordatorios de
      vencimiento (08:00), expiración de órdenes de pago (03:00), órdenes de renovación
      (03:30), complementos vencidos (02:00), revisión de SLA (cada 15 min) y auditoría de
      dependencias (lunes 04:00). Ver: `docker compose exec app php artisan schedule:list`.
- [ ] La operación de **actualizar dependencias** desde el panel corre en el worker: si el
      worker no está activo, se queda "En cola"
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
- [ ] El webhook de pagos debe ser alcanzable desde internet por HTTPS:
      `https://<tu-dominio>/api/webhooks/mercadopago` (es la URL que se registra en MercadoPago)

---

## 7. Seguridad / endurecimiento

- [ ] **HTTPS obligatorio**: proxy/terminador TLS delante de nginx, con redirección 80→443
- [ ] `SESSION_SECURE_COOKIE=true` y `APP_URL` en `https://`
- [ ] Puertos de **MySQL** (3306) y **Redis** (6379) **no** publicados a internet y
      **mailpit** sin arrancar: lo hace `docker-compose.prod.yml` (§4b). Compruébalo con
      `docker compose ps` (mysql/redis sin `0.0.0.0:...`, nginx en `127.0.0.1:8080`)
- [ ] `.env` (raíz y `src/`) fuera del control de versiones y con permisos restringidos
- [ ] `APP_KEY` respaldada en un gestor de secretos (no solo en el `.env`)
- [ ] Firewall del servidor: exponer solo 80/443 (y SSH restringido)
- [ ] Revisar que `/.env`, `/.git` no son accesibles por web (nginx ya deniega archivos ocultos)
- [ ] Redis con contraseña si queda accesible en red (`REDIS_PASSWORD`)
- [ ] La carpeta de respaldos de dependencias (`storage/app/dependency-backups`) contiene
      **volcados de la base de datos**: no la publiques ni la copies a lugares inseguros

---

## 8. Acceso inicial y roles

- [ ] Super administrador creado por `db:seed` (datos de `SUPERADMIN_*` del `.env`)
- [ ] Inicio de sesión correcto en el área admin
- [ ] (Opcional) Retirar `SUPERADMIN_PASSWORD` del `.env` tras crear el usuario
- [ ] Alta de admins/staff desde **Seguridad → Usuarios**
- [ ] Revisar roles/permisos en **Seguridad → Roles y permisos**
- [ ] Probar un alta de **cliente** y confirmar que el rol es `client` (y que llega el correo)
- [ ] Sub-usuarios del cliente: el permiso **"Hacer pedidos"** (`client.orders.create`) se
      asigna por sub-usuario desde **Mi Cuenta → Contactos** del cliente

---

## 9. Integración de hosting (WHM/cPanel)

- [ ] API Token creado en WHM (*WHM → Development → Manage API Tokens*)
- [ ] Servidor dado de alta (**Sistema → Servidores → Nuevo**): host, puerto `2087`, usuario `root`, token
- [ ] Opción de verificación TLS acorde al certificado del WHM
- [ ] **Guardar y probar conexión** → resultado correcto
- [ ] Vincular/crear una cuenta cPanel de prueba y verificar listado de correos
- [ ] Revisar **Integraciones → Logs** (eventos `whm.*`) sin errores

---

## 10. Configuración desde el panel

### Apariencia y correos (Sistema → Apariencia)
- [ ] Nombre, logo y favicon del portal
- [ ] **Datos del emisor** (razón social, RFC, domicilio, correo, teléfono): salen en los
      recibos de las órdenes de pago y en el pie de los correos
- [ ] **Correos electrónicos**: diseño con la marca activado, color y texto del pie. Usa la
      vista previa. Un cambio de logo o nombre se aplica solo en el siguiente correo.
- [ ] El logo de los correos se carga desde `APP_URL`: debe ser la URL pública HTTPS

### Modo de operación (Sistema → Modo de operación)
- [ ] **Pagos manual o automático.** Manual: la orden de pago se crea sin enlace y el
      cliente sube su comprobante. Automático: se conecta con la pasarela y el cliente
      paga en línea (requiere la integración de MercadoPago configurada).

### Integraciones (Sistema → Integraciones)
- [ ] **MercadoPago** (si los pagos son automáticos): credenciales, y en MercadoPago
      registrar el webhook `https://<tu-dominio>/api/webhooks/mercadopago`
- [ ] **WhatsApp (Meta Cloud API)**: `phone_number_id`, `access_token` y, opcional,
      `template_language` (por defecto `es_MX`)
- [ ] **Plantillas de WhatsApp aprobadas en Meta**, con estos nombres exactos (están fijos
      en el código; las variables se envían en este orden):
  - `invoice_available` (Utilidad) — {{1}} folio, {{2}} monto, {{3}} marca
  - `payment_proof_received` (Utilidad) — {{1}} cliente, {{2}} folio de la orden, {{3}} monto
  - `renewal_reminder`, `ticket_reply` y las demás que ya usaba el portal
- [ ] Mientras una plantilla no esté aprobada, ese WhatsApp falla; el correo sí sale
- [ ] **Slack** (opcional) para avisos internos de tickets

### Catálogo
- [ ] Productos con su **tipo** correcto (los de tipo **hosting** piden dominio al hacer el
      pedido) y su IVA por ciclo
- [ ] Ejecutivos de ventas con **correo y teléfono**, ligados a cada servicio: reciben el
      aviso cuando entra un comprobante de pago

---

## 11. Verificación funcional final

- [ ] Correo real probado (verificación de registro / recuperación de contraseña) → **llega**,
      con el diseño de la marca y el logo visible
- [ ] Cola procesando las dos colas (disparar una notificación y verificar que se envía)
- [ ] Scheduler activo: `php artisan schedule:list` muestra las tareas
- [ ] **Pagos y facturas**
  - [ ] Cliente: **Hacer un pedido** de un producto de hosting (pregunta por el dominio) →
        se genera la orden de pago y su recibo
  - [ ] Recibo de la orden en pantalla y en **PDF** (Pagos → Órdenes de pago → Recibo)
  - [ ] Cliente sube un comprobante → el **ejecutivo de ventas** recibe el aviso
  - [ ] Admin aprueba el comprobante → la orden queda **Pagada** y se genera la factura
  - [ ] Admin: **Generar orden** (cliente + servicio) y **Subir comprobante** a nombre de un cliente
  - [ ] Admin sube una factura (PDF/XML del SAT) → el **cliente** recibe el aviso
  - [ ] Pago en línea de prueba (si los pagos son automáticos) y conciliación por webhook
- [ ] Pantalla de **Gráficas** carga y exporta (Excel/CSV)
- [ ] Navegación entre secciones fluida (SPA con `wire:navigate`)
- [ ] 2FA: alta/activación/desactivación funcionan

---

## 12. Respaldos y monitoreo

- [ ] **Respaldo de base de datos** programado (dump periódico del volumen `mysql-data`),
      **fuera** del servidor. Los respaldos de la actualización de dependencias no lo
      sustituyen: viven en el mismo disco y solo se guardan 3.
- [ ] Respaldo seguro de `src/.env` + `APP_KEY`
- [ ] Rotación de logs (`storage/logs`) para que no crezcan sin límite
- [ ] Monitoreo básico: salud de contenedores, **espacio en disco** (los respaldos de
      dependencias crecen con la base de datos), uso de memoria
- [ ] Plan de restauración probado al menos una vez (restaurar un dump en un entorno aparte)

---

## 13. Actualizar dependencias desde el panel (Seguridad → Dependencias / Seguridad)

Cuando la auditoría encuentra vulnerabilidades aparece **Actualizar vulnerables**. Corre en
el worker: pone la app en mantenimiento, respalda base de datos + `vendor` + lockfiles +
assets, actualiza solo los paquetes vulnerables (sin `--force`), verifica que la app
arranque y, si algo falla, **restaura el código** sola. La base de datos **nunca** se
restaura sola (solo a mano, escribiendo `RESTAURAR`).

- [ ] Hacerlo en horario de bajo uso: la app queda unos minutos en mantenimiento
- [ ] ⚠️ **El servidor se separa de GitHub.** Después de actualizar, lleva al repositorio
      los `src/composer.lock` y `src/package-lock.json` del servidor; si no, el siguiente
      `git pull` + `composer install` deshace la actualización (o choca con ella)
- [ ] Plan B si la app no responde (desde el servidor):
  ```bash
  docker compose exec app php artisan dependencies:restore <id>             # código
  docker compose exec app php artisan dependencies:restore <id> --database  # base de datos (destructivo)
  docker compose exec app php artisan up                                    # salir de mantenimiento
  ```

---

## 14. Rutina de despliegues posteriores

Para cada actualización de código en producción:

```bash
git pull
docker compose build app                        # si cambió el Dockerfile o deps del sistema
docker compose up -d                            # recrea lo que cambió (incluye queue/scheduler)
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app npm ci && docker compose exec app npm run build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force   # permisos/datos base nuevos; no duplica
docker compose exec app php artisan storage:link      # si ya existe, solo avisa
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize          # config + rutas + vistas + eventos
docker compose exec app php artisan queue:restart
docker compose restart app queue scheduler            # PHP y workers toman el código nuevo
```

> Si cambiaste `docker-compose.yml` (p. ej. el comando del worker), `docker compose up -d`
> recrea el contenedor; un simple `restart` no aplica cambios del archivo.

---

### Comandos de diagnóstico útiles

```bash
docker compose ps                               # estado de contenedores
docker compose logs -f app                      # logs de la app
docker compose logs -f queue                    # trabajos de la cola (avisos, webhooks)
docker compose logs --tail=50 nginx             # logs de nginx (útil ante 502)
docker compose exec app php artisan about       # resumen de entorno/config
docker compose exec app php artisan schedule:list    # tareas programadas
docker compose exec app php artisan queue:failed     # trabajos que fallaron
docker compose exec app php -i | grep opcache   # estado de OPcache
docker compose exec app php artisan optimize:clear   # limpia todas las cachés
```
