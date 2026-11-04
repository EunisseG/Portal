<?php

namespace App\Console\Commands;

use App\Domain\System\Jobs\SendDependencyAuditNotification;
use App\Domain\System\Models\DependencyAudit;
use App\Domain\System\Services\DependencyAuditService;
use App\Integrations\Actions\RecordIntegrationLog;
use Illuminate\Console\Command;

/**
 * Audita semanalmente las dependencias (Composer + npm) y guarda un snapshot.
 * Registra el resultado en `integration_logs` y, si hay vulnerabilidades
 * críticas/altas NUEVAS respecto a la auditoría anterior, avisa al equipo. Así
 * no se repite la alerta semana tras semana por lo mismo: solo cuando algo
 * empeora.
 *
 * Pensado para correr semanalmente desde el scheduler.
 */
class CheckDependencyVulnerabilities extends Command
{
    protected $signature = 'dependencies:check';

    protected $description = 'Audita las dependencias (Composer y npm) y avisa al equipo si aparecen vulnerabilidades nuevas.';

    public function handle(DependencyAuditService $service, RecordIntegrationLog $logger): int
    {
        // Snapshot anterior (para comparar y detectar lo NUEVO).
        $previous = DependencyAudit::query()->latest('id')->first();

        $audit = $service->runAndStore();

        $logger->record('dependencies.audit', [
            'success' => ! $audit->has_errors && $audit->total_vulnerabilities === 0,
            'direction' => 'internal',
            'internal_reference' => 'dependency-audit-'.$audit->id,
            'message' => sprintf(
                'Auditoría de dependencias: %d vulnerabilidad(es) (Composer %d, npm %d)%s.',
                $audit->total_vulnerabilities,
                $audit->composer_total,
                $audit->npm_total,
                $audit->has_errors ? ' con errores de ejecución' : '',
            ),
        ]);

        // Solo avisamos si hay vulnerabilidades urgentes (críticas/altas) y
        // además empeoraron respecto a la corrida anterior.
        $urgent = $audit->urgentCount();
        $previousUrgent = $previous?->urgentCount() ?? 0;

        if ($urgent > 0 && $urgent > $previousUrgent) {
            SendDependencyAuditNotification::dispatch($audit->id);
            $this->info("Auditoría guardada. Vulnerabilidades urgentes: {$urgent} (antes {$previousUrgent}). Se avisó al equipo.");
        } else {
            $this->info("Auditoría guardada. Total: {$audit->total_vulnerabilities}. Urgentes: {$urgent} (antes {$previousUrgent}). Sin aviso nuevo.");
        }

        return self::SUCCESS;
    }
}
