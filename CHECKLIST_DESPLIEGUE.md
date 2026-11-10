# Checklist de despliegue — Portal WORTEV

Guía rápida paso a paso. Detalle completo en `DEPLOYMENT.md` y `CHECKLIST_PRODUCCION.md`;
recorrido concreto en DigitalOcean en `GUIA_DESPLIEGUE_DIGITALOCEAN.md`.

---

## Fase 1 — Antes de desplegar (preparación)

- [ ] Servidor con Docker + Docker Compose instalados
- [ ] Memoria: mínimo 2 GB de RAM + swap (recomendado 2 vCPU / 4 GB)
- [ ] Disco con margen para 3 respaldos completos (actualización de dependencias)
- [ ] Dominio apuntando al servidor (registro DNS A/AAAA)
- [ ] Certificado TLS/HTTPS listo (nginx o proxy delante)
- [ ] Acceso de red del portal al WHM de cada servidor (puerto `2087/tcp` saliente)
- [ ] Contraseñas fuertes definidas para MySQL (`DB_PASSWORD`, `DB_ROOT_PASSWORD`)
- [ ] Repositorio clonado en el servidor (`git clone https://github.com/EunisseG/Portal.git portal-wortev`)

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
- [ ] `SUPERADMIN_NAME/EMAIL/PASSWORD` definidos en `.env`
- [ ] `php artisan db:seed --force` (roles, super_admin, políticas SLA, áreas internas,
      encuestas; sin datos de demostración en producción)
- [ ] `php artisan storage:link` (sin esto no se ven el logo ni el favicon)
- [ ] `npm ci && npm run build`
- [ ] `php artisan optimize` (config + rutas + vistas + eventos)
- [ ] Contenedores `queue` y `scheduler` corriendo (`docker compose ps`)
- [ ] ⚠️ El worker procesa **`default` e `integrations`** (`--queue=default,integrations` en
      `docker-compose.yml`). Sin `integrations` no salen avisos y **no se confirman los pagos en línea**.

## Fase 4 — Endurecer producción

- [ ] Ajustes de producción activos: `COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml`
      en el `.env` de la raíz (mysql/redis sin publicar, nginx solo en `127.0.0.1`, sin mailpit)
- [ ] En servidores pequeños: swap activo y `MYSQL_BUFFER_POOL` (256M con 2 GB, 512M con 4 GB)
      (ver `CHECKLIST_PRODUCCION.md` §4b)
- [ ] Sitio servido solo por HTTPS (redirección 80→443)
- [ ] `.env` y `APP_KEY` fuera de control de versiones y respaldados

## Fase 5 — Acceso inicial

- [ ] Super administrador creado con `InitialUsersSeeder` (datos del `.env`)
- [ ] Inicio de sesión correcto en el área de administración
- [ ] `SUPERADMIN_PASSWORD` retirada del `.env` tras crear el usuario (opcional)
- [ ] Alta de tus admins/staff desde Seguridad → Usuarios
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
- [ ] **OPcache activo** (ya viene habilitado en la imagen vía `docker/php/php.ini`).
  - ⚠️ **No** uses `opcache.validate_timestamps=0`: la actualización de dependencias desde
    el panel reemplaza `vendor` sin reiniciar PHP-FPM y PHP seguiría con el código viejo.
  - Verificar que está activo: `docker compose exec app php -i | grep opcache.enable`
    (debe decir `On`).
- [ ] Cachés de framework generadas (reconstruir en **cada** deploy):
  ```bash
  docker compose exec app php artisan optimize:clear
  docker compose exec app php artisan optimize    # config + rutas + vistas + eventos
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

## Fase 7.8 — Configuración en el panel

- [ ] Sistema → Apariencia: logo, nombre, **datos del emisor** y diseño de **correos**
- [ ] Sistema → Modo de operación: pagos manuales o automáticos
- [ ] Sistema → Integraciones → SMTP: correo real configurado y probado
- [ ] MercadoPago (si pagos automáticos): webhook `https://<tu-dominio>/api/webhooks/mercadopago`
- [ ] WhatsApp: plantillas **`invoice_available`** y **`payment_proof_received`** aprobadas en Meta
- [ ] Ejecutivos de ventas con correo y teléfono, ligados a sus servicios

## Fase 8 — Verificación final

- [ ] Envío de correo real probado (recuperación de contraseña / notificación), con el logo visible
- [ ] Cola procesando las dos colas (crear algo que dispare un aviso y verificar)
- [ ] Scheduler activo: `php artisan schedule:list`
- [ ] Pedido del cliente → orden de pago → recibo en PDF
- [ ] Comprobante del cliente → aviso al ejecutivo → aprobación → orden pagada y factura generada
- [ ] Factura subida → aviso al cliente
- [ ] Respaldo de base de datos programado (fuera del servidor)
- [ ] Respaldo seguro de `.env` + `APP_KEY`

## Fase 9 — Actualizaciones posteriores

- [ ] Rutina de re-deploy de `CHECKLIST_PRODUCCION.md` §14 (incluye `db:seed`, `storage:link`
      y reiniciar `queue`/`scheduler`)
- [ ] Si se actualizaron dependencias **desde el panel**: subir `src/composer.lock` y
      `src/package-lock.json` del servidor a GitHub antes del siguiente `git pull`
- [ ] Emergencia: `php artisan dependencies:restore <id>` (código) o `--database` (destructivo)

---

### Comandos útiles de diagnóstico

```bash
docker compose ps                     # estado de contenedores
docker compose logs -f app            # logs de la app
docker compose exec app php artisan about   # resumen de entorno/config
docker compose exec app php artisan queue:restart   # tras cada deploy
docker compose exec app php artisan optimize:clear  # limpia TODAS las cachés (config/route/view/event)
docker compose exec app php artisan optimize        # regenera config+route+view+event cache de una vez
docker compose exec app php artisan schedule:list   # tareas programadas
docker compose exec app php artisan queue:failed    # trabajos de la cola que fallaron
docker compose logs -f queue                        # actividad del worker
```
