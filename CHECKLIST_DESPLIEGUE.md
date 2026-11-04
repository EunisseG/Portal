# Checklist de despliegue — Portal WORTEV

Guía rápida paso a paso. Detalle completo en `DEPLOYMENT.md`.

---

## Fase 1 — Antes de desplegar (preparación)

- [ ] Servidor con Docker + Docker Compose instalados
- [ ] Dominio apuntando al servidor (registro DNS A/AAAA)
- [ ] Certificado TLS/HTTPS listo (nginx o proxy delante)
- [ ] Acceso de red del portal al WHM de cada servidor (puerto `2087/tcp` saliente)
- [ ] Contraseñas fuertes definidas para MySQL (`DB_PASSWORD`, `DB_ROOT_PASSWORD`)
- [ ] Repositorio clonado en el servidor

## Fase 2 — Configuración (`src/.env`)

- [ ] `src/.env` creado a partir de `src/.env.example`
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://portal.tu-dominio.com`
- [ ] Datos de `DB_*` correctos
- [ ] `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`
- [ ] Correo de producción configurado (`MAIL_MAILER` real, **no** mailpit)
- [ ] ⚠️ `APP_KEY` generada **una sola vez** y **respaldada** (si cambia, se pierden los tokens WHM cifrados)

## Fase 3 — Primer despliegue

- [ ] `docker compose build`
- [ ] `docker compose up -d`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan key:generate` (solo la primera vez)
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder --force`
- [ ] `SUPERADMIN_NAME/EMAIL/PASSWORD` definidos en `.env`
- [ ] `php artisan db:seed --class=Database\\Seeders\\InitialUsersSeeder --force` (crea super_admin)
- [ ] `npm ci && npm run build`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Contenedores `queue` y `scheduler` corriendo (`docker compose ps`)

## Fase 4 — Endurecer producción

- [ ] `mailpit` retirado o no expuesto públicamente
- [ ] Puertos de `mysql` y `redis` **no** publicados a internet
- [ ] Sitio servido solo por HTTPS (redirección 80→443)
- [ ] `.env` y `APP_KEY` fuera de control de versiones y respaldados

## Fase 5 — Acceso inicial

- [ ] Super administrador creado con `InitialUsersSeeder` (datos del `.env`)
- [ ] Inicio de sesión correcto en el área de administración
- [ ] `SUPERADMIN_PASSWORD` retirada del `.env` tras crear el usuario (opcional)
- [ ] Alta de tus admins/staff desde Sistema → Usuarios
- [ ] Registro de clientes disponible en `/register` (o Clientes → Nuevo)
- [ ] Revisar que roles/permisos se ven bien (Seguridad → Roles y permisos)

## Fase 6 — Conectar hosting (WHM/cPanel)

- [ ] API Token creado en WHM (*WHM → Development → Manage API Tokens*)
- [ ] Servidor dado de alta (Sistema → Servidores → Nuevo): gestor WHM, host, puerto 2087, usuario `root`, token
- [ ] "Verificar TLS" configurado según el certificado del WHM
- [ ] **Guardar y probar conexión** → resultado correcto

## Fase 7 — Vincular servicios y probar correos

- [ ] Un servicio de hosting vinculado a su cuenta cPanel:
  - Cuenta nueva → **Crear cuenta en WHM**
  - Cuenta existente → **Vincular cuenta existente** (verifica que exista)
- [ ] Admin: detalle del servicio → **Consultar correos** muestra los buzones con uso/cuota
- [ ] Cliente: **Mis servicios → Ver detalle** muestra sus correos (solo lectura)
- [ ] Revisar Integraciones → Logs (eventos `whm.list_email`, `whm.link_account`) sin errores
- [ ] Si el formato de cPanel difiere, ajustar `normalizeMailboxes` en `WhmHostingProvider.php`

## Fase 7.5 — Rendimiento (producción)

El portal es notablemente más rápido en producción cuando se activan estas
optimizaciones. En desarrollo se dejan desactivadas a propósito (estorban al
depurar); aquí sí deben estar todas.

- [ ] `APP_ENV=production` y `APP_DEBUG=false` en `src/.env` (lo más importante: en
      modo debug cada petición es mucho más lenta)
- [ ] **OPcache activo** (ya viene habilitado en la imagen vía
      `docker/php/php.ini`). En producción conviene afinarlo:
  - `opcache.validate_timestamps=0` → no revalida archivos en cada request (más
    rápido). Requiere **recargar PHP-FPM tras cada deploy** para que tome el
    código nuevo: `docker compose restart app`.
  - Verificar que está activo: `docker compose exec app php -i | grep opcache.enable`
    (debe decir `On`).
- [ ] Cachés de framework generadas (reconstruir en **cada** deploy, en este orden):
  ```bash
  docker compose exec app php artisan config:cache
  docker compose exec app php artisan route:cache
  docker compose exec app php artisan event:cache
  docker compose exec app php artisan view:cache
  ```
- [ ] `composer install --no-dev --optimize-autoloader` (autoloader optimizado)
- [ ] `npm ci && npm run build` (assets compilados y minificados; **no** usar el
      servidor de desarrollo de Vite en producción)
- [ ] Tras cada despliegue, reiniciar workers para que tomen el código nuevo:
  ```bash
  docker compose exec app php artisan queue:restart
  docker compose restart app queue scheduler
  ```

> Nota: si cambias el `.env` o el código con `config:cache`/`route:cache`
> activos, los cambios **no** se aplican hasta regenerar esas cachés. Si algo
> "no toma efecto" en producción, ejecuta `php artisan optimize:clear` y vuelve
> a cachear.

## Fase 8 — Verificación final

- [ ] Envío de correo real probado (recuperación de contraseña / notificación)
- [ ] Cola procesando (crear algo que dispare notificación y verificar)
- [ ] Scheduler activo (recordatorios de vencimiento)
- [ ] Respaldo de base de datos programado
- [ ] Respaldo seguro de `.env` + `APP_KEY`

---

### Comandos útiles de diagnóstico

```bash
docker compose ps                     # estado de contenedores
docker compose logs -f app            # logs de la app
docker compose exec app php artisan about   # resumen de entorno/config
docker compose exec app php artisan queue:restart   # tras cada deploy
docker compose exec app php artisan optimize:clear  # limpia TODAS las cachés (config/route/view/event)
docker compose exec app php artisan optimize        # regenera config+route+view cache de una vez
```
