<?php

namespace App\Console\Commands;

use App\Domain\System\Enums\DependencyUpdateStatus;
use App\Domain\System\Enums\DependencyUpdateType;
use App\Domain\System\Models\DependencyUpdate;
use App\Domain\System\Services\DependencyUpdateService;
use Illuminate\Console\Command;

/**
 * Restaura desde la consola del servidor un respaldo de dependencias. Es el
 * plan B cuando la app no responde y no se puede usar el panel. Corre en el
 * momento (sin cola).
 *
 *   php artisan dependencies:restore 12              # código (vendor, lockfiles, assets)
 *   php artisan dependencies:restore 12 --database   # base de datos
 */
class RestoreDependencyBackup extends Command
{
    protected $signature = 'dependencies:restore
        {update : Id de la actualización cuyo respaldo se usa}
        {--database : Restaurar la base de datos en lugar del código}';

    protected $description = 'Restaura el código o la base de datos desde un respaldo de actualización de dependencias.';

    public function handle(DependencyUpdateService $service): int
    {
        $source = DependencyUpdate::find((int) $this->argument('update'));

        if (! $source || ! $source->hasBackup()) {
            $this->error('No existe una actualización con respaldo disponible con ese id.');
            $this->listBackups();

            return self::FAILURE;
        }

        $database = (bool) $this->option('database');

        if ($database) {
            $this->warn('Se reemplazará la base de datos por la del '.$source->created_at->format('d/m/Y H:i').'.');
            $this->warn('Se PERDERÁ todo lo registrado después de esa fecha (pagos, tickets, pedidos…).');
            if (! $this->confirm('¿Continuar?', false)) {
                $this->info('Cancelado.');

                return self::SUCCESS;
            }
        }

        // Si quedó una operación "en curso" colgada, se da por terminada.
        DependencyUpdate::whereIn('status', [DependencyUpdateStatus::Queued->value, DependencyUpdateStatus::Running->value])
            ->update([
                'status' => DependencyUpdateStatus::Failed->value,
                'summary' => 'Cerrada al restaurar desde la consola.',
                'finished_at' => now(),
                'bypass_secret' => null,
            ]);

        $operation = DependencyUpdate::create([
            'type' => ($database ? DependencyUpdateType::RestoreDatabase : DependencyUpdateType::RestoreCode)->value,
            'status' => DependencyUpdateStatus::Queued->value,
            'source_update_id' => $source->id,
        ]);

        $this->info('Restaurando… esto puede tardar varios minutos.');
        $service->perform($operation->id);

        $operation->refresh();
        $this->line($operation->summary ?? '');

        return $operation->status === DependencyUpdateStatus::Succeeded ? self::SUCCESS : self::FAILURE;
    }

    private function listBackups(): void
    {
        $rows = DependencyUpdate::where('type', DependencyUpdateType::Update->value)
            ->whereNotNull('backup_path')
            ->whereNull('backup_pruned_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (DependencyUpdate $u) => [$u->id, $u->created_at->format('d/m/Y H:i'), $u->status->label(), $u->backupSizeLabel()])
            ->all();

        if ($rows !== []) {
            $this->table(['Id', 'Fecha', 'Estado', 'Tamaño'], $rows);
        }
    }
}
