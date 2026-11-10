# Guía de despliegue — Portal WORTEV

Guía para poner el portal en producción y empezar a conectar servidores y servicios de hosting (WHM/cPanel).

> El proyecto Laravel vive en `src/`. La orquestación (Docker) está en la raíz del repo.

---

## 1. Requisitos del servidor

- Docker y Docker Compose, **o** PHP 8.3+, Composer, Node 22 LTS, MySQL 8 y Redis 7 si se despliega sin contenedores.
- Memoria: **mínimo 2 GB de RAM + swap**; recomendado **2 vCPU / 4 GB**. Ver `CHECKLIST_PRODUCCION.md` §0 y §4b.
- Un dominio apuntando al servidor y un certificado TLS (HTTPS) para el panel.
- Acceso de red saliente del portal hacia el puerto de WHM de cada servidor (por defecto `2087/tcp`),
  y hacia internet (Composer/npm, MercadoPago, WhatsApp de Meta, SMTP).

> Lista completa paso a paso: **`CHECKLIST_PRODUCCION.md`**.
> Recorrido concreto en DigitalOcean: **`GUIA_DESPLIEGUE_DIGITALOCEAN.md`**.

---

## 2. Variables de entorno (`.env` de producción)

Copia `src/.env.example` a `src/.env` y ajusta. Valores clave para **producción**:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://portal.tu-dominio.com

# Genera una clave propia y NO la vuelvas a cambiar (ver aviso abajo).
APP_KEY=            # se genera con: php artisan key:generate

# Base de datos
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=portal_wortev
DB_USERNAME=wortev
DB_PASSWORD=<contraseña-fuerte>
DB_ROOT_PASSWORD=<contraseña-root-fuerte>

# Caché, colas y sesión en Redis (recomendado)
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379

# Correo: configúralo desde el panel (Sistema → Integraciones → SMTP)
# o define aquí un MAIL_MAILER real. En producción NO uses mailpit.
MAIL_MAILER=smtp
```

> ⚠️ **APP_KEY es crítica.** Los tokens de WHM de cada servidor se guardan **cifrados** con `APP_KEY`. Si cambias `APP_KEY` después de guardar tokens, todos dejarán de poder descifrarse y tendrás que recapturarlos. Genera la clave una sola vez y respáldala de forma segura.

---

## 3. Primer despliegue

Desde la raíz del repo:

```bash
# 1. Construir e iniciar contenedores
docker compose build
docker compose up -d

# 2. Dependencias PHP (dentro del contenedor app)
docker compose exec app composer install --no-dev --optimize-autoloader

# 3. Clave de la app (solo la primera vez; respáldala)
docker compose exec app php artisan key:generate

# 4. Migraciones
docker compose exec app php artisan migrate --force

# 5. Datos base (idempotente; seguro de re-ejecutar). Crea roles y permisos, el
#    super admin (requiere SUPERADMIN_* en el .env; ver §5), políticas SLA, áreas
#    internas y encuestas. En producción NO siembra datos de demostración.
docker compose exec app php artisan db:seed --force

# 6. Enlace de archivos públicos (logo, favicon). public/storage no viaja en git.
docker compose exec app php artisan storage:link

# 7. Compilar assets del frontend
docker compose exec app npm ci
docker compose exec app npm run build

# 8. Cachear configuración/rutas/vistas/eventos para producción
docker compose exec app php artisan optimize
```

### Procesos en segundo plano

Los contenedores `queue` (worker de colas) y `scheduler` (tareas programadas) ya están definidos en `docker-compose.yml` y arrancan solos con `up -d`.

- **`queue`** procesa **dos** colas: `--queue=default,integrations`. En `integrations` viajan los
  avisos de factura y comprobante, WhatsApp, Slack, alertas de SLA, mantenimientos y **el webhook de
  MercadoPago**. Si el worker no procesa esa cola, esos avisos no salen y los pagos en línea nunca se
  confirman.
- **`scheduler`** corre `php artisan schedule:work`. Ver las tareas: `php artisan schedule:list`.

### Endurecer para producción
- Activa los ajustes de producción: en el `.env` de la raíz del servidor pon
  `COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml`. Con eso `mysql`/`redis` no se
  publican, nginx escucha solo en `127.0.0.1`, `mailpit` no arranca y MySQL limita su memoria
  (`MYSQL_BUFFER_POOL`).
- Sirve detrás de HTTPS (termina TLS en el nginx del host o en un proxy delante).
- En servidores pequeños activa swap (`CHECKLIST_PRODUCCION.md` §4b).
- Recorrido concreto en un droplet: **`GUIA_DESPLIEGUE_DIGITALOCEAN.md`**.

---

## 4. Actualizaciones (re-deploy)

```bash
git pull
docker compose up -d                                # aplica cambios de docker-compose.yml (p. ej. el worker)
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force # permisos y datos base nuevos; no duplica
docker compose exec app php artisan storage:link    # si ya existe, solo avisa
docker compose exec app npm ci && docker compose exec app npm run build
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
docker compose exec app php artisan queue:restart   # recarga los workers con el código nuevo
docker compose restart app queue scheduler
```

> **Si actualizaste dependencias desde el panel** (Seguridad → Dependencias / Seguridad), el
> servidor tiene `src/composer.lock` y `src/package-lock.json` distintos a GitHub. Llévalos al
> repositorio **antes** del siguiente `git pull`, o el despliegue deshará la actualización.
> Detalle y comandos de emergencia (`dependencies:restore`): `CHECKLIST_PRODUCCION.md` §13.

---

## 5. Crear el primer administrador (super_admin)

El super administrador inicial se crea con un seeder que lee sus datos del `.env`.
Define estas variables **antes** de correr el seeder:

```dotenv
SUPERADMIN_NAME="Tu Nombre"
SUPERADMIN_EMAIL=admin@tu-dominio.com
SUPERADMIN_PASSWORD=<contraseña-fuerte>
```

Luego ejecuta (ya incluido en `db:seed --force` del §3; también se puede correr solo):

```bash
docker compose exec app php artisan db:seed --class=Database\\Seeders\\InitialUsersSeeder --force
```

Notas:
- Es **idempotente**: si el usuario ya existe, no lo duplica ni cambia su contraseña (solo verifica el rol). Puedes borrar `SUPERADMIN_PASSWORD` del `.env` después de crearlo.
- Si no defines email/contraseña, el seeder se omite con un aviso (no falla el deploy).
- El resto de administradores (`admin`), `staff`, `support` o `billing` los das de alta **desde el panel** (Seguridad → Usuarios) una vez dentro.
- Los **clientes** se registran solos en `https://portal.tu-dominio.com/register` (crea rol `client` + su perfil), o los das de alta desde Clientes → Nuevo.

Roles disponibles: `super_admin`, `admin`, `staff`, `support`, `billing`, `client`, `employee`.

> Los seeders de datos de demostración (`DemoUsersSeeder`, etc.) **no** se ejecutan en producción: `DatabaseSeeder` los omite cuando `APP_ENV=production`.

---

## 6. Conectar un servidor WHM/cPanel

1. Entra como admin → **Sistema → Servidores → Nuevo**.
2. Completa la conexión del panel:
   - **Gestor**: WHM
   - **Host**: el hostname del WHM (p. ej. `srv1.tu-hosting.com`)
   - **Puerto**: `2087` (WHM sobre HTTPS)
   - **Usuario**: normalmente `root`
   - **Token de API**: crea un **API Token** en WHM (*WHM → Development → Manage API Tokens*) y pégalo aquí. Se guarda cifrado.
   - **Verificar TLS**: actívalo si el WHM tiene un certificado válido; desactívalo solo si usa uno autofirmado.
3. Pulsa **Guardar y probar conexión**. Debe reportar conexión correcta (hace una llamada `listpkgs`).

> El token nunca se muestra de vuelta. Al editar, déjalo vacío para conservar el actual, o escribe uno nuevo para reemplazarlo.

---

## 7. Vincular servicios a sus cuentas cPanel

Para que el cliente vea sus correos, cada servicio de hosting debe estar ligado a un usuario de cPanel (`whm_username`).

- **Cuentas nuevas**: en el detalle del servicio (admin), usa **Crear cuenta en WHM**. Se crea la cuenta y se vincula automáticamente.
- **Cuentas que ya existen** en tu cPanel: en el detalle del servicio, sección *Hosting (WHM)* → **Vincular cuenta existente**. Escribe el usuario cPanel; el sistema **verifica que la cuenta exista** en ese servidor antes de guardar el vínculo.
- **Desvincular**: usa **Desvincular** si te equivocaste (no borra nada en el servidor, solo quita el vínculo en el portal).

Una vez vinculado, en el detalle del servicio aparece la sección **Correos** (admin), y el cliente ve sus buzones y uso/cuota desde **Mis servicios → Ver detalle**.

---

## 8. Verificar la lectura de correos contra tu WHM real

El listado de buzones usa la función de cPanel `Email::list_pops_with_disk` a través de WHM. El formato de respuesta puede variar según la versión de cPanel.

1. Vincula un servicio a una cuenta cPanel real (paso 7).
2. En el detalle del servicio (admin) pulsa **Consultar correos**, o entra como el cliente y abre el detalle del servicio.
3. Si los buzones aparecen con su uso/cuota, todo bien. Si algún dato sale vacío o hay error:
   - Revisa **Sistema → Integraciones → Logs** (eventos `whm.list_email`).
   - El normalizador de la respuesta está en un solo lugar: `src/app/Integrations/Providers/Whm/WhmHostingProvider.php` (`normalizeMailboxes`). Si tu versión de cPanel devuelve otros nombres de campo, se ajusta ahí.

> Los buzones se cachean 120 s por servicio (Redis). El botón **Actualizar** fuerza una consulta fresca.

---

## 9. Notas de seguridad

- Los tokens de panel se guardan cifrados (`APP_KEY`) y nunca se serializan ni se muestran.
- Cada rol solo ve lo que le corresponde (permisos por rol). El cliente solo ve **sus** servicios y correos.
- Trata `.env` y `APP_KEY` como secretos: fuera del control de versiones y respaldados.
- Da a los API Tokens de WHM los privilegios mínimos necesarios para las operaciones usadas (crear/suspender cuentas y leer correo).

---

## 10. Pagos, avisos y correos

Configuración que se hace desde el panel tras el primer despliegue (detalle en `CHECKLIST_PRODUCCION.md` §10):

- **Sistema → Apariencia**: logo, nombre, **datos del emisor** (salen en los recibos) y el diseño de los
  **correos** (color y pie, con vista previa). Los cambios de logo o nombre se aplican solos en el
  siguiente correo. El logo de los correos se carga desde `APP_URL`, que debe ser la URL pública HTTPS.
- **Sistema → Modo de operación**: pagos **manuales** (el cliente sube su comprobante) o **automáticos**
  (paga en línea con MercadoPago).
- **Sistema → Integraciones**:
  - **MercadoPago**: credenciales; en MercadoPago registra el webhook
    `https://<tu-dominio>/api/webhooks/mercadopago`.
  - **WhatsApp (Meta)**: las plantillas deben estar **aprobadas en Meta** con estos nombres exactos:
    `invoice_available` ({{1}} folio, {{2}} monto, {{3}} marca) y `payment_proof_received`
    ({{1}} cliente, {{2}} folio de la orden, {{3}} monto), además de las que ya usaba el portal.
- **Avisos automáticos**: al subir una factura se avisa al **cliente**; al entrar un comprobante, al
  **ejecutivo de ventas** del servicio. Ambos por correo y WhatsApp, a través de la cola `integrations`.
