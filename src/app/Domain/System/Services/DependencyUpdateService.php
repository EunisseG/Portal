<?php

namespace App\Domain\System\Services;

use App\Domain\System\Enums\DependencyUpdateStatus;
use App\Domain\System\Enums\DependencyUpdateType;
use App\Domain\System\Jobs\RunDependencyOperation;
use App\Domain\System\Models\DependencyAudit;
use App\Domain\System\Models\DependencyUpdate;
use App\Models\User;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Actualiza las dependencias vulnerables desde el panel, con respaldo previo y
 * reversión automática si algo falla.
 *
 * Flujo de una actualización:
 *  1. Auditoría previa (qué paquetes son vulnerables ahora mismo).
 *  2. Modo mantenimiento (el admin que la lanzó sigue entrando con su cookie).
 *  3. Respaldo: base de datos, composer/package json+lock, vendor y assets
 *     compilados. Si el respaldo falla, NO se toca nada.
 *  4. Actualiza solo los paquetes vulnerables, respetando las restricciones de
 *     composer.json / package.json (sin saltos de versión mayor ni --force).
 *  5. Recompila assets, regenera cachés y verifica que la app arranque.
 *  6. Si algo falla en 4–5, restaura el código del respaldo. La base de datos
 *     NUNCA se restaura sola: es una acción manual con confirmación.
 *  7. Auditoría posterior, sale de mantenimiento y reinicia los workers.
 *
 * Se conservan solo los últimos N respaldos (config dependencies.keep_backups).
 * Todos los comandos externos pasan por Process para poder simularlos en tests.
 */
class DependencyUpdateService
{
    /** Formato válido de un paquete de Composer (vendor/nombre). */
    private const COMPOSER_PACKAGE = '/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$/';

    /** Archivos de manifiesto/lock que se respaldan y restauran tal cual. */
    private const MANIFESTS = ['composer.json', 'composer.lock', 'package.json', 'package-lock.json'];

    /** Máximo de caracteres que se guardan de la bitácora. */
    private const LOG_LIMIT = 60000;

    public function __construct(private readonly DependencyAuditService $audits) {}

    // =====================================================================
    // Encolar operaciones (lo llama el panel)
    // =====================================================================

    /** ¿Hay una operación en cola o en curso? Solo se permite una a la vez. */
    public function hasActiveOperation(): bool
    {
        return DependencyUpdate::whereIn('status', [
            DependencyUpdateStatus::Queued->value,
            DependencyUpdateStatus::Running->value,
        ])->exists();
    }

    /** Encola una actualización de los paquetes vulnerables. */
    public function queueUpdate(User $user): DependencyUpdate
    {
        return $this->queue(DependencyUpdateType::Update, $user);
    }

    /** Encola la restauración (de código o de base de datos) desde un respaldo. */
    public function queueRestore(DependencyUpdate $source, DependencyUpdateType $type, User $user): DependencyUpdate
    {
        if ($type === DependencyUpdateType::Update) {
            throw new RuntimeException('Tipo de restauración inválido.');
        }

        if (! $source->hasBackup()) {
            throw new RuntimeException('Ese respaldo ya no está disponible.');
        }

        return $this->queue($type, $user, $source);
    }

    private function queue(DependencyUpdateType $type, User $user, ?DependencyUpdate $source = null): DependencyUpdate
    {
        if ($this->hasActiveOperation()) {
            throw new RuntimeException('Ya hay una operación de dependencias en curso. Espera a que termine.');
        }

        $operation = DependencyUpdate::create([
            'type' => $type->value,
            'status' => DependencyUpdateStatus::Queued->value,
            'source_update_id' => $source?->id,
            'user_id' => $user->id,
            // Secreto para que quien la lanzó siga entrando en mantenimiento.
            'bypass_secret' => Str::random(40),
        ]);

        RunDependencyOperation::dispatch($operation->id);

        return $operation;
    }

    // =====================================================================
    // Ejecutar (lo llama el job o el comando de consola)
    // =====================================================================

    /**
     * Ejecuta la operación. Reclama la operación de forma atómica: si ya la
     * tomó otro proceso (p. ej. la cola la reentregó), no hace nada.
     */
    public function perform(int $operationId): void
    {
        $claimed = DependencyUpdate::whereKey($operationId)
            ->where('status', DependencyUpdateStatus::Queued->value)
            ->update([
                'status' => DependencyUpdateStatus::Running->value,
                'started_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $operation = DependencyUpdate::findOrFail($operationId);

        try {
            match ($operation->type) {
                DependencyUpdateType::Update => $this->runUpdate($operation),
                DependencyUpdateType::RestoreCode => $this->runRestoreCode($operation),
                DependencyUpdateType::RestoreDatabase => $this->runRestoreDatabase($operation),
            };
        } catch (Throwable $e) {
            // Red de seguridad: cualquier error no previsto deja constancia y
            // saca la app de mantenimiento.
            $this->log($operation, 'ERROR inesperado: '.$e->getMessage());
            $this->maintenanceUp($operation);
            $this->finish($operation, DependencyUpdateStatus::Failed, 'La operación falló: '.$e->getMessage());
        }
    }

    /**
     * La cola interrumpió la operación (p. ej. superó el tiempo máximo). El
     * código puede haber quedado a medias, así que la app se deja en
     * mantenimiento para no exponer errores: hay que restaurar el código.
     */
    public function markInterrupted(int $operationId, string $reason): void
    {
        $operation = DependencyUpdate::find($operationId);

        if (! $operation || ! $operation->status->isActive()) {
            return;
        }

        $this->log($operation, 'Operación interrumpida: '.$reason);
        $operation->maintenance_left_on = true;
        $this->finish(
            $operation,
            DependencyUpdateStatus::Failed,
            'La operación se interrumpió y la app quedó en mantenimiento. Restaura el código desde el panel '
                .'(o en el servidor: php artisan dependencies:restore '.($operation->source_update_id ?? $operation->id).').',
        );
    }

    // ---------------------------------------------------------------------
    // Actualización
    // ---------------------------------------------------------------------

    private function runUpdate(DependencyUpdate $operation): void
    {
        // 1. Auditoría previa: qué es vulnerable ahora mismo.
        $this->log($operation, 'Revisando dependencias vulnerables…');
        $before = $this->audits->runAndStore();
        $packages = $this->vulnerablePackages($before);
        $operation->forceFill([
            'audit_before_id' => $before->id,
            'packages' => $packages,
        ])->save();

        if ($packages['composer'] === [] && ! $packages['npm']) {
            $this->finish($operation, DependencyUpdateStatus::Succeeded, 'No había paquetes vulnerables que actualizar.');

            return;
        }

        // 2. Mantenimiento.
        $this->maintenanceDown($operation);

        // 3. Respaldo. Si falla, no se modificó nada.
        try {
            $this->ensureDiskSpace();
            $backupDir = $this->createBackup($operation);
            $operation->forceFill([
                'backup_path' => $backupDir,
                'backup_size_bytes' => $this->directorySize($backupDir),
            ])->save();
            $this->pruneBackups();
        } catch (Throwable $e) {
            $this->log($operation, 'ERROR en el respaldo: '.$e->getMessage());
            $this->maintenanceUp($operation);
            $this->finish($operation, DependencyUpdateStatus::Failed,
                'No se pudo crear el respaldo, así que no se actualizó nada. '.$e->getMessage());

            return;
        }

        // 4–5. Actualizar y verificar; si falla, restaurar el código.
        $caches = $this->detectCaches();

        try {
            if ($packages['composer'] !== []) {
                $this->updateComposer($operation, $packages['composer']);
            }
            if ($packages['npm']) {
                $this->updateNpm($operation);
            }
            $this->rebuildCaches($operation, $caches);
            $this->healthCheck($operation);
        } catch (Throwable $e) {
            $this->log($operation, 'ERROR en la actualización: '.$e->getMessage());
            $this->log($operation, 'Restaurando el código del respaldo…');

            try {
                $this->restoreCodeFrom($operation, $backupDir, $caches);
                $summary = 'La actualización falló y se restauró el código anterior. La base de datos no se tocó. '
                    .'Motivo: '.$e->getMessage();
            } catch (Throwable $rollback) {
                $this->log($operation, 'ERROR al restaurar el código: '.$rollback->getMessage());
                $summary = 'La actualización falló y NO se pudo restaurar el código automáticamente. '
                    .'Restaura desde el panel o en el servidor con: php artisan dependencies:restore '.$operation->id;
            }

            $this->restartWorkers($operation);
            $this->maintenanceUp($operation);
            $this->finish($operation, DependencyUpdateStatus::Failed, $summary);

            return;
        }

        // 6. Auditoría posterior y salida.
        $this->log($operation, 'Revisando de nuevo las dependencias…');
        $after = $this->audits->runAndStore();
        $operation->forceFill(['audit_after_id' => $after->id])->save();

        $this->restartWorkers($operation);
        $this->maintenanceUp($operation);

        $summary = sprintf(
            'Actualización completada. Vulnerabilidades: %d antes, %d después.',
            $before->total_vulnerabilities,
            $after->total_vulnerabilities,
        );
        if ($after->total_vulnerabilities > 0) {
            $summary .= ' Las restantes no tienen corrección compatible y requieren revisión manual.';
        }

        $this->finish($operation, DependencyUpdateStatus::Succeeded, $summary);
    }

    /**
     * Paquetes a actualizar según la auditoría. Composer: nombres validados.
     * npm: basta saber si hay vulnerabilidades (se usa `npm audit fix`).
     *
     * @return array{composer: list<string>, npm: bool}
     */
    private function vulnerablePackages(DependencyAudit $audit): array
    {
        $composer = collect($audit->detail['composer']['packages'] ?? [])
            ->pluck('package')
            ->filter(fn ($name) => is_string($name) && preg_match(self::COMPOSER_PACKAGE, $name) === 1)
            ->unique()
            ->values()
            ->all();

        return [
            'composer' => $composer,
            'npm' => (int) $audit->npm_total > 0,
        ];
    }

    /**
     * Actualiza solo los paquetes indicados (y sus dependencias), dentro de
     * las restricciones de composer.json. Respeta si el servidor está sin
     * paquetes de desarrollo (instalación con --no-dev).
     *
     * @param  list<string>  $packages
     */
    private function updateComposer(DependencyUpdate $operation, array $packages): void
    {
        $this->log($operation, 'Actualizando paquetes de Composer: '.implode(', ', $packages));

        [$direct, $transitive] = $this->splitComposerPackages($packages);

        if ($transitive !== []) {
            $this->log($operation, 'Transitivos (se actualizan vía sus dependientes): '.implode(', ', $transitive));
        }

        // Los directos se nombran; para alcanzar los transitivos se incluyen
        // sus dependencias. Si solo hay transitivos, se actualiza todo el árbol
        // respetando las restricciones de composer.json (sin --force).
        $targets = $direct !== [] ? $direct : [];

        $command = array_merge(
            ['composer', 'update'],
            $targets,
            ['--with-all-dependencies', '--no-interaction', '--no-progress'],
        );

        if (! $this->composerDevInstalled()) {
            $command[] = '--no-dev';
            $command[] = '--optimize-autoloader';
        }

        $this->exec($operation, $command, 900);
    }

    private function updateNpm(DependencyUpdate $operation): void
    {
        $this->log($operation, 'Aplicando correcciones de npm (sin --force)…');

        // `npm audit fix` termina con error si quedan vulnerabilidades sin
        // corrección compatible; eso no es un fallo de la actualización.
        $this->exec($operation, ['npm', 'audit', 'fix', '--no-fund'], 900, mustSucceed: false);

        $this->log($operation, 'Compilando los assets…');
        $this->exec($operation, ['npm', 'run', 'build'], 900);
    }

    // ---------------------------------------------------------------------
    // Restauraciones manuales
    // ---------------------------------------------------------------------

    private function runRestoreCode(DependencyUpdate $operation): void
    {
        $source = $operation->source;
        if (! $source || ! $source->hasBackup()) {
            $this->finish($operation, DependencyUpdateStatus::Failed, 'El respaldo ya no está disponible.');

            return;
        }

        $this->maintenanceDown($operation);
        $caches = $this->detectCaches();

        try {
            $this->restoreCodeFrom($operation, $source->backup_path, $caches);
        } catch (Throwable $e) {
            $this->log($operation, 'ERROR: '.$e->getMessage());
            $this->restartWorkers($operation);
            $this->maintenanceUp($operation);
            $this->finish($operation, DependencyUpdateStatus::Failed, 'No se pudo restaurar el código: '.$e->getMessage());

            return;
        }

        $this->restartWorkers($operation);
        $this->maintenanceUp($operation);
        $this->finish($operation, DependencyUpdateStatus::Succeeded,
            'Código restaurado al respaldo del '.$source->created_at?->format('d/m/Y H:i').'.');
    }

    private function runRestoreDatabase(DependencyUpdate $operation): void
    {
        $source = $operation->source;
        $dump = $source?->backup_path ? $source->backup_path.'/database.sql.gz' : null;

        if (! $source || ! $source->hasBackup() || ! $this->artifactIsValid($dump)) {
            $this->finish($operation, DependencyUpdateStatus::Failed, 'El respaldo de la base de datos no está disponible.');

            return;
        }

        $this->maintenanceDown($operation);

        try {
            $this->log($operation, 'Restaurando la base de datos…');
            $db = $this->databaseConfig();
            $command = sprintf(
                'gunzip -c %s | mysql --host=%s --port=%s --user=%s %s',
                escapeshellarg($dump),
                escapeshellarg($db['host']),
                escapeshellarg($db['port']),
                escapeshellarg($db['username']),
                escapeshellarg($db['database']),
            );
            $this->exec($operation, ['bash', '-o', 'pipefail', '-c', $command], 1500, env: ['MYSQL_PWD' => $db['password']]);

            // La caché puede tener datos (p. ej. permisos) de la base anterior.
            $this->exec($operation, ['php', 'artisan', 'cache:clear'], 120, mustSucceed: false);
            $this->healthCheck($operation);
        } catch (Throwable $e) {
            $this->log($operation, 'ERROR: '.$e->getMessage());
            $this->maintenanceUp($operation);
            $this->finish($operation, DependencyUpdateStatus::Failed, 'No se pudo restaurar la base de datos: '.$e->getMessage());

            return;
        }

        $this->maintenanceUp($operation);
        $this->finish($operation, DependencyUpdateStatus::Succeeded,
            'Base de datos restaurada al respaldo del '.$source->created_at?->format('d/m/Y H:i').'.');
    }

    // =====================================================================
    // Respaldo y restauración de código
    // =====================================================================

    /** Crea la carpeta de respaldo y devuelve su ruta. Lanza si algo falla. */
    private function createBackup(DependencyUpdate $operation): string
    {
        $dir = rtrim($this->backupRoot(), '/').'/'.$operation->id.'-'.now()->format('Ymd_His');
        File::ensureDirectoryExists($dir, 0750);

        $this->log($operation, 'Respaldando la base de datos…');
        $this->dumpDatabase($operation, $dir.'/database.sql.gz');

        $this->log($operation, 'Respaldando archivos de dependencias…');
        foreach (self::MANIFESTS as $file) {
            if (File::exists(base_path($file))) {
                File::copy(base_path($file), $dir.'/'.$file);
            }
        }

        $this->exec($operation, ['tar', '-czf', $dir.'/vendor.tar.gz', '-C', base_path(), 'vendor'], 900);
        if (! $this->artifactIsValid($dir.'/vendor.tar.gz')) {
            throw new RuntimeException('El respaldo de vendor está vacío o dañado.');
        }

        if (File::isDirectory(public_path('build'))) {
            $this->exec($operation, ['tar', '-czf', $dir.'/build.tar.gz', '-C', base_path(), 'public/build'], 300);
            if (! $this->artifactIsValid($dir.'/build.tar.gz')) {
                throw new RuntimeException('El respaldo de los assets está vacío o dañado.');
            }
        }

        $this->log($operation, 'Respaldo listo en '.$dir);

        return $dir;
    }

    private function dumpDatabase(DependencyUpdate $operation, string $target): void
    {
        $db = $this->databaseConfig();

        $ignore = collect(config('dependencies.excluded_tables', []))
            ->map(fn (string $table) => '--ignore-table='.escapeshellarg($db['database'].'.'.$table))
            ->implode(' ');

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --quick --routines --no-tablespaces %s %s | gzip > %s',
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['username']),
            $ignore,
            escapeshellarg($db['database']),
            escapeshellarg($target),
        );

        // La contraseña va por variable de entorno: no aparece en el comando ni en la bitácora.
        $this->exec($operation, ['bash', '-o', 'pipefail', '-c', $command], 1200, env: ['MYSQL_PWD' => $db['password']]);

        if (! $this->artifactIsValid($target)) {
            throw new RuntimeException('El respaldo de la base de datos está vacío o dañado.');
        }
    }

    /**
     * Devuelve vendor, lockfiles y assets al estado del respaldo. Verifica los
     * archivos ANTES de borrar nada.
     *
     * @param  array<string, bool>  $caches
     */
    private function restoreCodeFrom(DependencyUpdate $operation, string $dir, array $caches): void
    {
        $vendorArchive = $dir.'/vendor.tar.gz';
        if (! $this->artifactIsValid($vendorArchive)) {
            throw new RuntimeException('El respaldo de vendor no está disponible o está dañado.');
        }

        $buildArchive = $dir.'/build.tar.gz';
        $hasBuild = File::exists($buildArchive);
        if ($hasBuild && ! $this->artifactIsValid($buildArchive)) {
            throw new RuntimeException('El respaldo de los assets está dañado.');
        }

        $this->log($operation, 'Restaurando archivos de dependencias…');
        foreach (self::MANIFESTS as $file) {
            if (File::exists($dir.'/'.$file)) {
                File::copy($dir.'/'.$file, base_path($file));
            }
        }

        $this->log($operation, 'Restaurando vendor…');
        $this->exec($operation, ['rm', '-rf', base_path('vendor')], 300);
        $this->exec($operation, ['tar', '-xzf', $vendorArchive, '-C', base_path()], 900);

        if ($hasBuild) {
            $this->log($operation, 'Restaurando assets compilados…');
            $this->exec($operation, ['rm', '-rf', public_path('build')], 120);
            $this->exec($operation, ['tar', '-xzf', $buildArchive, '-C', base_path()], 300);
        }

        // node_modules solo se usa para compilar; se resincroniza con el lock
        // restaurado, sin que un fallo aquí afecte a la app en ejecución.
        $this->exec($operation, ['npm', 'ci', '--no-audit', '--no-fund'], 900, mustSucceed: false);

        $this->rebuildCaches($operation, $caches);
        $this->healthCheck($operation);
        $this->log($operation, 'Código restaurado.');
    }

    /** Conserva solo los N respaldos más recientes; borra el resto del disco. */
    public function pruneBackups(): void
    {
        $keep = max(1, (int) config('dependencies.keep_backups', 3));

        $withBackup = DependencyUpdate::where('type', DependencyUpdateType::Update->value)
            ->whereNotNull('backup_path')
            ->whereNull('backup_pruned_at')
            ->orderByDesc('id')
            ->get();

        foreach ($withBackup->slice($keep) as $old) {
            $this->deleteBackupDirectory($old->backup_path);
            $old->forceFill(['backup_pruned_at' => now()])->save();
        }
    }

    /** Borra una carpeta de respaldo, solo si está dentro de la carpeta de respaldos. */
    private function deleteBackupDirectory(?string $path): void
    {
        if (! $path || ! File::isDirectory($path)) {
            return;
        }

        $root = realpath($this->backupRoot());
        $real = realpath($path);

        if ($root && $real && str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($real);
        }
    }

    // =====================================================================
    // Pasos auxiliares
    // =====================================================================

    private function maintenanceDown(DependencyUpdate $operation): void
    {
        $this->log($operation, 'Activando modo mantenimiento…');
        $command = ['php', 'artisan', 'down', '--retry=60'];
        if ($operation->bypass_secret) {
            $command[] = '--secret='.$operation->bypass_secret;
        }
        $this->exec($operation, $command, 60, mustSucceed: false, logCommand: false);
    }

    private function maintenanceUp(DependencyUpdate $operation): void
    {
        $this->log($operation, 'Saliendo del modo mantenimiento…');
        $this->exec($operation, ['php', 'artisan', 'up'], 60, mustSucceed: false);
    }

    /** Los workers de cola cargan el código nuevo al terminar su trabajo actual. */
    private function restartWorkers(DependencyUpdate $operation): void
    {
        $this->exec($operation, ['php', 'artisan', 'queue:restart'], 60, mustSucceed: false);
    }

    /**
     * Qué cachés de framework existen ahora, para regenerar las mismas.
     *
     * @return array<string, bool>
     */
    private function detectCaches(): array
    {
        return [
            'config' => File::exists(base_path('bootstrap/cache/config.php')),
            'route' => File::exists(base_path('bootstrap/cache/routes-v7.php')),
            'event' => File::exists(base_path('bootstrap/cache/events.php')),
        ];
    }

    /** @param  array<string, bool>  $caches */
    private function rebuildCaches(DependencyUpdate $operation, array $caches): void
    {
        $this->exec($operation, ['php', 'artisan', 'optimize:clear'], 300);

        foreach ($caches as $name => $wasCached) {
            if ($wasCached) {
                $this->exec($operation, ['php', 'artisan', $name.':cache'], 300);
            }
        }
    }

    /** La app debe arrancar completa (proveedores, rutas, config) con el código nuevo. */
    private function healthCheck(DependencyUpdate $operation): void
    {
        $this->log($operation, 'Verificando que la aplicación arranque…');
        $this->exec($operation, ['php', 'artisan', 'about', '--json'], 120);
        $this->exec($operation, ['php', 'artisan', 'route:list', '--json'], 120);
    }

    /**
     * Separa los paquetes vulnerables en directos (están en composer.json) y
     * transitivos (dependencias de otros). Los directos se pueden nombrar en
     * `composer update`; los transitivos se alcanzan con --with-all-dependencies.
     *
     * @param  list<string>  $packages
     * @return array{0: list<string>, 1: list<string>}
     */
    private function splitComposerPackages(array $packages): array
    {
        $declared = [];
        $json = json_decode((string) File::get(base_path('composer.json')), true);
        if (is_array($json)) {
            $declared = array_map('strtolower', array_merge(
                array_keys((array) ($json['require'] ?? [])),
                array_keys((array) ($json['require-dev'] ?? [])),
            ));
        }

        $direct = [];
        $transitive = [];
        foreach ($packages as $package) {
            if (in_array(strtolower($package), $declared, true)) {
                $direct[] = $package;
            } else {
                $transitive[] = $package;
            }
        }

        return [$direct, $transitive];
    }

    /** ¿El servidor tiene instalados los paquetes de desarrollo de Composer? */
    private function composerDevInstalled(): bool
    {
        $installed = base_path('vendor/composer/installed.json');
        if (! File::exists($installed)) {
            return true;
        }

        $data = json_decode((string) File::get($installed), true);

        return ! is_array($data) || ($data['dev'] ?? true) !== false;
    }

    private function ensureDiskSpace(): void
    {
        $required = $this->estimatedBackupBytes() + (int) config('dependencies.min_free_margin');
        $free = $this->freeBytes();

        if ($free !== null && $free < $required) {
            throw new RuntimeException(sprintf(
                'Espacio insuficiente para el respaldo: se necesitan unos %d MB y hay %d MB libres.',
                (int) ($required / 1048576),
                (int) ($free / 1048576),
            ));
        }
    }

    /** Tamaño aproximado de la base de datos (MySQL) para estimar el respaldo. */
    private function estimatedBackupBytes(): int
    {
        try {
            $db = $this->databaseConfig();
            $row = DB::selectOne(
                'SELECT COALESCE(SUM(data_length + index_length), 0) AS bytes FROM information_schema.tables WHERE table_schema = ?',
                [$db['database']],
            );

            return (int) ($row->bytes ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array{host:string, port:string, database:string, username:string, password:string}
     */
    protected function databaseConfig(): array
    {
        $name = config('database.default');
        $config = config("database.connections.{$name}");

        if (! is_array($config) || ! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('El respaldo de base de datos solo está disponible con MySQL/MariaDB.');
        }

        return [
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (string) ($config['port'] ?? '3306'),
            'database' => (string) ($config['database'] ?? ''),
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
        ];
    }

    private function backupRoot(): string
    {
        return (string) config('dependencies.backup_path');
    }

    private function directorySize(string $dir): int
    {
        return (int) collect(File::files($dir))->sum(fn ($file) => $file->getSize());
    }

    /**
     * Corre un comando y lo anota en la bitácora. Lanza si falla (salvo que
     * $mustSucceed sea false). La salida se recorta para no crecer sin límite.
     *
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    protected function exec(
        DependencyUpdate $operation,
        array $command,
        int $timeout,
        bool $mustSucceed = true,
        array $env = [],
        bool $logCommand = true,
    ): ProcessResult {
        $label = $logCommand ? $this->describe($command) : $command[0].' '.($command[1] ?? '').' '.($command[2] ?? '').' …';

        $result = Process::path(base_path())->timeout($timeout)->env($env)->run($command);

        $output = trim($result->output()."\n".$result->errorOutput());
        $this->log($operation, '$ '.$label.($output !== '' ? "\n".Str::limit($output, 3000) : ''));

        if ($mustSucceed && $result->failed()) {
            throw new RuntimeException(sprintf('«%s» terminó con código %d.', $label, $result->exitCode()));
        }

        return $result;
    }

    /** Versión legible del comando para la bitácora (nunca incluye contraseñas). */
    private function describe(array $command): string
    {
        // En los comandos por bash, el script ya no contiene la contraseña.
        if (($command[0] ?? null) === 'bash') {
            return (string) end($command);
        }

        return implode(' ', $command);
    }

    private function log(DependencyUpdate $operation, string $line): void
    {
        $entry = '['.now()->format('H:i:s').'] '.$line;
        $log = trim(($operation->log ?? '')."\n".$entry);

        if (strlen($log) > self::LOG_LIMIT) {
            $log = '…'.substr($log, -self::LOG_LIMIT);
        }

        $operation->forceFill(['log' => $log])->save();
    }

    private function finish(DependencyUpdate $operation, DependencyUpdateStatus $status, string $summary): void
    {
        $this->log($operation, $summary);
        $operation->forceFill([
            'status' => $status->value,
            'summary' => $summary,
            'finished_at' => now(),
            'bypass_secret' => null, // ya no se necesita
        ])->save();
    }

    // ---------------------------------------------------------------------
    // Puntos de extensión (sustituibles en pruebas)
    // ---------------------------------------------------------------------

    /** ¿El archivo existe, no está vacío y (si es .gz) no está dañado? */
    protected function artifactIsValid(?string $path): bool
    {
        if (! $path || ! File::exists($path) || File::size($path) === 0) {
            return false;
        }

        if (str_ends_with($path, '.gz')) {
            return Process::timeout(600)->run(['gzip', '-t', $path])->successful();
        }

        return true;
    }

    /** Espacio libre en la carpeta de respaldos (bytes), o null si no se sabe. */
    protected function freeBytes(): ?int
    {
        $root = $this->backupRoot();
        File::ensureDirectoryExists($root, 0750);
        $free = @disk_free_space($root);

        return $free === false ? null : (int) $free;
    }
}
