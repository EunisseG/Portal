# Guía — Acelerar el entorno en Windows (WSL2)

En Windows con Docker Desktop, el proyecto va lento porque el código vive en el
disco de Windows (`C:\proyectos\...`) y Docker lo lee a través del puente
Windows↔WSL2, que es muy lento para apps PHP (Laravel abre cientos de archivos
por petición). **La solución es mover el proyecto DENTRO de WSL2**, donde el
disco es Linux nativo. Las páginas pasan de ~18 s a < 1 s.

> Esto solo afecta tu máquina de desarrollo. **No cambia nada para producción**:
> el servidor Linux ya usa disco nativo y el despliegue (git + docker) es igual.

---

## Parte A — Controlar la memoria de WSL2 (`.wslconfig`)

Síntoma: errores tipo *"No hay suficientes recursos de memoria"* al usar `wsl`
o lentitud general del equipo. Por defecto WSL2 puede tomar demasiada RAM.

1. Cierra **Docker Desktop** (clic derecho en el icono → Quit).
2. Crea el archivo `C:\Users\TU_USUARIO\.wslconfig` con este contenido
   (ajusta `memory` a ~la mitad de tu RAM física; ejemplos abajo):

   ```ini
   [wsl2]
   # Memoria máxima que WSL2 (y Docker) puede usar.
   # Equipo con 8 GB  -> memory=4GB
   # Equipo con 16 GB -> memory=8GB
   # Equipo con 32 GB -> memory=12GB
   memory=4GB
   processors=4
   swap=2GB
   ```

3. Reinicia WSL para aplicar:
   ```powershell
   wsl --shutdown
   ```
4. Vuelve a abrir Docker Desktop.

> Deja a Windows al menos 4 GB libres. Si pones `memory` demasiado alto, Windows
> se queda sin RAM (es justo el error que viste).

---

## Parte B — Mover el proyecto a WSL2 (lo que elimina la lentitud)

### Requisitos previos
- Docker Desktop con **WSL2 backend** activado
  (Settings → General → *Use the WSL 2 based engine*).
- Una distro de Linux instalada (Ubuntu recomendada). Si no tienes:
  ```powershell
  wsl --install -d Ubuntu
  ```
- En VS Code / Kiro, instalar la extensión **WSL** (Microsoft) para editar
  archivos dentro de WSL sin fricción.

### Pasos

1. **Haz commit o respaldo** de tu trabajo actual (por seguridad):
   ```powershell
   cd C:\proyectos\portal-wortev
   git status
   git add -A ; git commit -m "wip: antes de mover a WSL2"   # si usas git
   ```

2. Abre una terminal de tu distro (escribe `wsl` en PowerShell, o abre "Ubuntu"
   desde el menú inicio).

3. Dentro de WSL, crea una carpeta de proyectos en tu HOME de Linux
   (filesystem nativo = rápido). **No** uses `/mnt/c/...` (eso es el disco de
   Windows, igual de lento):
   ```bash
   mkdir -p ~/proyectos
   cd ~/proyectos
   ```

4. Trae el proyecto. **Opción recomendada: clonar desde git** (más limpio):
   ```bash
   git clone <URL-de-tu-repo> portal-wortev
   cd portal-wortev
   ```
   Si no usas git, copia la carpeta desde Windows (una sola vez):
   ```bash
   cp -r /mnt/c/proyectos/portal-wortev ~/proyectos/portal-wortev
   cd ~/proyectos/portal-wortev
   ```

5. Recrea los archivos de entorno (no suelen venir en git):
   ```bash
   cp .env.example .env               # entorno Docker (raíz)
   cp src/.env.example src/.env       # entorno Laravel
   ```
   Luego edita `src/.env` con tus valores (o copia los que ya tenías).
   ⚠️ Conserva el **mismo `APP_KEY`** que ya usabas, o los datos cifrados
   (tokens WHM, contraseña SMTP guardada) dejarán de poder descifrarse.

6. Levanta los contenedores **desde la ruta WSL** (no desde `C:\`):
   ```bash
   docker compose up -d --build
   docker compose exec app composer install
   docker compose exec app npm ci && docker compose exec app npm run build
   docker compose exec app php artisan migrate --force
   ```
   Si traes datos de tu BD actual, restaura el dump que ya tienes:
   `backup_portal_20261014.sql` (ver más abajo cómo).

7. Abre el proyecto en el editor **desde WSL**:
   - En la terminal de WSL, dentro de la carpeta:
     ```bash
     code .
     ```
   - Se abrirá VS Code conectado a WSL (abajo a la izquierda dirá `WSL: Ubuntu`).
   - La ruta también es accesible desde el explorador de Windows como:
     `\\wsl$\Ubuntu\home\TU_USUARIO\proyectos\portal-wortev`

8. Verifica la mejora: abre el portal en el navegador. Debe cargar en < 1 s.

### Restaurar tu base de datos actual (opcional)
Si quieres conservar los datos que ya tienes (los 2 clientes, etc.), usa el
respaldo `backup_portal_20261014.sql`:

```bash
# copia el .sql dentro de la nueva carpeta del proyecto en WSL, luego:
docker compose exec -T mysql sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < backup_portal_20261014.sql
```

---

## Notas

- A partir de ahora trabaja **siempre** desde la ruta WSL
  (`~/proyectos/portal-wortev`), no desde `C:\proyectos\...`.
- Puedes borrar la copia vieja en `C:\proyectos\portal-wortev` cuando confirmes
  que todo funciona desde WSL (o déjala como respaldo).
- Git funciona igual dentro de WSL.
- Para producción no hay que hacer nada de esto: el servidor Linux ya es nativo.

---

## Si prefieres NO mover el proyecto (alternativa parcial)

Menos efectiva, pero sin mover nada: montar `vendor/` como *named volume* de
Docker (I/O nativo) dejando el resto bind-montado. Mejora bastante aunque no
tanto como mover todo a WSL2, y obliga a resincronizar tras cada
`composer install`. Pídelo y se configura en `docker-compose.yml`.
