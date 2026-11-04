<?php

namespace App\Console\Commands;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Jobs\SendSlaBreachNotification;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\SlaResolver;
use App\Integrations\Actions\RecordIntegrationLog;
use Illuminate\Console\Command;

/**
 * Revisa los tickets activos y detecta los que incumplen su SLA (primera
 * respuesta o resolución) resuelto por capas (cliente > global). Por cada
 * incumplimiento NUEVO registra el evento en `integration_logs` y avisa al
 * equipo interno, marcando `sla_breach_alerted_at` para no repetir la alerta
 * en cada corrida. Si un ticket vuelve a estar en tiempo (p. ej. se le subió
 * el objetivo por override del cliente) se limpia la marca para poder volver a
 * avisar si vuelve a vencer.
 *
 * Pensado para correr cada pocos minutos desde el scheduler.
 */
class CheckSlaBreaches extends Command
{
    protected $signature = 'sla:check';

    protected $description = 'Detecta tickets activos que incumplen su SLA y avisa al equipo (sin duplicar alertas).';

    public function handle(SlaResolver $resolver, RecordIntegrationLog $logger): int
    {
        $tickets = Ticket::query()
            ->where('status', '!=', TicketStatus::Closed->value)
            ->with('client.user')
            ->get();

        $alerted = 0;

        foreach ($tickets as $ticket) {
            $frt = $resolver->firstResponseStatus($ticket);
            $res = $resolver->resolutionStatus($ticket);
            $breached = $frt === 'breached' || $res === 'breached';

            if (! $breached) {
                // Sin incumplimiento vigente: si traía marca, se limpia para
                // poder volver a avisar en el futuro.
                if ($ticket->sla_breach_alerted_at !== null) {
                    $ticket->update(['sla_breach_alerted_at' => null]);
                }

                continue;
            }

            // Ya se avisó de este incumplimiento: no repetir.
            if ($ticket->sla_breach_alerted_at !== null) {
                continue;
            }

            $kinds = [];
            if ($frt === 'breached') {
                $kinds[] = 'primera respuesta';
            }
            if ($res === 'breached') {
                $kinds[] = 'resolución';
            }
            $kindLabel = implode(' y ', $kinds);

            $logger->record('sla.breach', [
                'success' => false,
                'direction' => 'internal',
                'internal_reference' => $ticket->number,
                'message' => sprintf('SLA incumplido (%s) en ticket %s.', $kindLabel, $ticket->number),
            ]);

            SendSlaBreachNotification::dispatch($ticket->id, $kinds);

            $ticket->update(['sla_breach_alerted_at' => now()]);
            $alerted++;
        }

        $this->info("Tickets con SLA incumplido avisados: {$alerted}.");

        return self::SUCCESS;
    }
}
