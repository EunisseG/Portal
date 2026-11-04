<?php

namespace App\Domain\System\Jobs;

use App\Domain\System\Models\DependencyAudit;
use App\Integrations\Contracts\NotificationGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa al equipo interno por Slack del resultado de una auditoría de
 * dependencias cuando hay vulnerabilidades (en especial críticas/altas). Lo
 * dispara el comando `dependencies:check`. Se encola. Usa el NotificationGateway
 * (Slack real si está configurado; si no, el fake que solo registra).
 */
class SendDependencyAuditNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $auditId)
    {
        $this->onQueue('integrations');
    }

    public function handle(NotificationGateway $notifications): void
    {
        $audit = DependencyAudit::find($this->auditId);

        if (! $audit) {
            return;
        }

        $notifications->notify(sprintf(
            "🛡️ Auditoría de dependencias\n*Total:* %d vulnerabilidad(es)\n*Composer:* %d (críticas %d, altas %d)\n*npm:* %d (críticas %d, altas %d)\nRevisa el panel: Sistema → Dependencias / Seguridad.",
            $audit->total_vulnerabilities,
            $audit->composer_total,
            $audit->composer_critical,
            $audit->composer_high,
            $audit->npm_total,
            $audit->npm_critical,
            $audit->npm_high,
        ));
    }
}
