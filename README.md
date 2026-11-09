# Portal WORTEV

Aplicación web desarrollada con **Laravel / PHP**, con un entorno de desarrollo local totalmente **Dockerizado**.

Este repositorio contiene, por ahora, únicamente el entorno de desarrollo. Aún no se han implementado funcionalidades del sistema ni integraciones con servicios externos (WHM, dominios, SMTP real, WhatsApp, etc.).

---

## Requisitos previos

Solo necesitas esto instalado en tu equipo Windows. **Nada más se instala en Windows**: PHP, Composer, Node, MySQL y Redis viven dentro de los contenedores.

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) con Docker Compose (backend WSL 2 recomendado).
- Que Docker Desktop esté **corriendo** ("Engine running") antes de ejecutar los comandos.

Verifica que Docker responde:

```powershell
docker --version
docker compose version
```

---

## Arquitectura del entorno

El entorno se compone de 4 contenedores orquestados con Docker Compose:

| Servicio | Imagen                | Rol                                                        | Puerto host |
|----------|-----------------------|------------------------------------------------------------|-------------|
| `app`    | build local (PHP 8.3) | PHP-FPM + Composer + Node/npm. Aquí corre Laravel.         | interno 9000 |
| `nginx`  | nginx:1.27-alpine     | Servidor web. Expone el sitio.                             | `8080`      |
| `mysql`  | mysql:8.0             | Base de datos.                                             | `3306`      |
| `redis`  | redis:7-alpine        | Colas, caché y notificaciones (preparado para uso futuro). | `6379`      |

El código de Laravel vive en `./src` y se monta dentro del contenedor `app` en `/var/www/html`.

---

## Estructura del proyecto

```
portal-wortev/
├── docker-compose.yml        # Orquesta los 4 servicios, redes y volúmenes
├── .env                      # Variables del entorno Docker (generado desde .env.example)
├── .env.example              # Plantilla de variables (sin credenciales reales)
├── .gitignore
├── Makefile                  # Atajos opcionales (make up, make shell, ...)
├── README.md
├── docker/
│   ├── php/Dockerfile        # Imagen PHP-FPM + extensiones + Composer + Node
│   ├── nginx/default.conf    # Virtual host de Nginx para Laravel
│   └── mysql/my.cnf          # Ajustes básicos de MySQL
└── src/                      # Proyecto Laravel
```

---

## Puesta en marcha (primera vez)

Desde la raíz del proyecto (`portal-wortev/`):

```powershell
# 1. Crear el archivo de entorno Docker a partir de la plantilla
Copy-Item .env.example .env

# 2. Construir la imagen de la app (PHP + Composer + Node). Tarda unos minutos.
docker compose build

# 3. Levantar todos los servicios
docker compose up -d

# 4. Ejecutar las migraciones de la base de datos
docker compose exec app php artisan migrate
```

Abre el navegador en:

```
http://localhost:8080
```

Deberías ver la página de bienvenida de Laravel.

> Nota: el proyecto Laravel de `./src` ya viene instalado en este repositorio. Los pasos anteriores levantan y validan el entorno. Si `./src` estuviera vacío, consulta la sección "Reinstalar Laravel desde cero".

---

## Uso diario

```powershell
docker compose up -d      # Levantar el entorno
docker compose down       # Detener el entorno
docker compose ps         # Ver estado de los contenedores
docker compose logs -f    # Ver logs en vivo
```

### Ejecutar comandos dentro del contenedor

Todos los comandos de PHP/Composer/Node se ejecutan **dentro** del contenedor `app`:

```powershell
# Abrir una shell en el contenedor app
docker compose exec app bash

# Artisan
docker compose exec app php artisan list
docker compose exec app php artisan migrate

# Composer
docker compose exec app composer install

# Node / npm (assets del frontend)
docker compose exec app npm install
docker compose exec app npm run dev
```

### Atajos con Make (opcional)

Si tienes `make` disponible:

```powershell
make up          # docker compose up -d
make down        # docker compose down
make shell       # shell en el contenedor app
make artisan cmd="migrate"
make composer cmd="install"
make npm cmd="install"
```

---

## Acceso a la base de datos y a Redis

Credenciales de desarrollo (definidas en `.env`, **no son reales**):

- **MySQL**: host `localhost`, puerto `3306`, base `portal_wortev`, usuario `wortev`, contraseña `secret`.
- **Redis**: host `localhost`, puerto `6379`, sin contraseña.

Clientes desde la terminal:

```powershell
docker compose exec mysql mysql -uwortev -psecret portal_wortev
docker compose exec redis redis-cli
```

Desde Laravel (`src/.env`) los servicios se referencian por su **nombre de contenedor**, no por `localhost`:

- `DB_HOST=mysql`
- `REDIS_HOST=redis`

---

## Configuración de Laravel (`src/.env`)

El entorno ya está preconfigurado para el stack Docker:

- `DB_CONNECTION=mysql`, `DB_HOST=mysql`
- `REDIS_HOST=redis`
- `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`
- `MAIL_MAILER=log` — los correos se escriben en el log, **no se envía nada** (sin SMTP real).

Redis queda listo para colas, caché y notificaciones, pero aún no se usa en ninguna funcionalidad.

---

## Puertos ocupados

Si `8080`, `3306` o `6379` ya están en uso en tu Windows, cámbialos en el archivo `.env` de la raíz:

```
APP_PORT=8081
DB_PORT=33060
REDIS_PORT=63790
```

Luego reinicia: `docker compose down; docker compose up -d`.

---

## Reinstalar Laravel desde cero

Si `./src` está vacío y necesitas reinstalar el framework dentro del contenedor:

```powershell
docker compose up -d mysql redis
docker compose run --rm --user root app bash -c "composer create-project laravel/laravel /tmp/laravel --no-interaction && cp -a /tmp/laravel/. /var/www/html/ && rm -rf /tmp/laravel && chown -R appuser:appuser /var/www/html"
docker compose up -d
docker compose exec app php artisan migrate
```

Recuerda ajustar `src/.env` (DB_HOST=mysql, REDIS_HOST=redis) como se indica arriba.

---

## Limpieza total

```powershell
docker compose down -v    # Detiene y elimina también los volúmenes (borra datos de MySQL/Redis)
```

---

## Estado actual

- [x] Entorno Docker (app, nginx, mysql, redis) funcional.
- [x] Laravel instalado y sirviendo en `http://localhost:8080`.
- [x] MySQL conectado y migraciones base ejecutadas.
- [x] Redis conectado (caché/colas/sesión preparados).
- [ ] Funcionalidades del sistema (pendiente).
- [ ] Integraciones externas: WHM, dominios, SMTP real, WhatsApp (pendiente, no conectadas).

## Procesos en segundo plano (colas y programador)

El entorno incluye dos contenedores que corren solos:

- **queue**: procesa la cola de Redis (notificaciones de tickets, recordatorios).
  Equivale a `php artisan queue:work`.
- **scheduler**: deja corriendo el planificador de Laravel
  (`php artisan schedule:work`), que dispara las tareas programadas: recordatorios
  de vencimiento (`reminders:send`, 08:00), expiración de órdenes de pago
  (`payment-orders:expire`, 03:00) y generación de órdenes de renovación
  (`payment-orders:generate-renewals`, 03:30).

Ambos se levantan con `docker compose up -d` junto al resto. Para ver su actividad:

```powershell
docker compose logs queue -f
docker compose logs scheduler -f
```

Ejecutar los recordatorios manualmente (por ejemplo, para probar):

```powershell
docker compose exec app php artisan reminders:send
```

Los recordatorios se envían por correo (al cliente y a `RENEWALS_NOTIFY_EMAIL`) y por
WhatsApp mediante adaptadores **fake** (se registran en el log; no se envía nada real
hasta conectar proveedores). La tabla `reminders` evita reenviar el mismo aviso.

## Actualización de dependencias (Dependencias / Seguridad)

Desde **Sistema → Dependencias / Seguridad**, cuando hay vulnerabilidades, aparece
**Actualizar vulnerables**. La operación corre en segundo plano (contenedor `queue`):

1. Audita y detecta los paquetes vulnerables.
2. Pone la app en mantenimiento (quien la lanzó sigue entrando con su sesión).
3. Respalda base de datos, `vendor`, lockfiles y assets compilados en
   `storage/app/dependency-backups` (se conservan los 3 más recientes).
4. Actualiza solo los paquetes vulnerables (sin `--force` ni saltos de versión mayor),
   recompila y verifica que la app arranque.
5. Si algo falla, restaura el código automáticamente. **La base de datos nunca se
   restaura sola.**

Los respaldos permiten **restaurar el código** (seguro) o **restaurar la base de datos**
(destructivo: pide escribir `RESTAURAR`; se pierde lo registrado después del respaldo).

### Restauración de emergencia por consola

Si la app quedó en mantenimiento y no se puede usar el panel, desde el servidor:

```bash
# Listar respaldos disponibles y restaurar el código de la actualización #<id>
docker compose exec app php artisan dependencies:restore <id>

# Restaurar también la base de datos (destructivo; pide confirmación)
docker compose exec app php artisan dependencies:restore <id> --database

# Salir del modo mantenimiento
docker compose exec app php artisan up
```
