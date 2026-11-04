<?php

namespace App\Domain\Tickets\Services;

use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Models\ClientSlaPolicy;
use App\Domain\Tickets\Models\SlaPolicy;
use App\Domain\Tickets\Models\Ticket;

/**
 * Resuelve el SLA aplicable a un ticket en capas, de lo más específico a lo más
 * general:
 *   1) SLA del cliente para esa criticidad (client_sla_policies).
 *   2) SLA global para esa criticidad (sla_policies).
 * Es la ÚNICA fuente de verdad para métricas y semáforo.
 */
class SlaResolver
{
    /**
     * Objetivos (en minutos) para un ticket. null si no hay política definida.
     *
     * @return array{first_response_minutes: ?int, resolution_minutes: ?int, source: string}
     */
    public function forTicket(Ticket $ticket): array
    {
        $criticality = $ticket->criticality ?? TicketCriticality::Medium;
        $type = $ticket->type ?? \App\Domain\Tickets\Enums\TicketType::Client;

        // El override por cliente solo aplica a tickets de cliente; los internos
        // usan directamente la política global de su tipo.
        $clientId = $type === \App\Domain\Tickets\Enums\TicketType::Internal ? null : $ticket->client_id;

        return $this->forClientCriticality($clientId, $criticality, $type);
    }

    /**
     * @return array{first_response_minutes: ?int, resolution_minutes: ?int, source: string}
     */
    public function forClientCriticality(?int $clientId, TicketCriticality $criticality, \App\Domain\Tickets\Enums\TicketType $type = \App\Domain\Tickets\Enums\TicketType::Client): array
    {
        // 1) Override del cliente (solo tickets de cliente).
        if ($clientId !== null) {
            $client = ClientSlaPolicy::query()
                ->where('client_id', $clientId)
                ->where('criticality', $criticality->value)
                ->first();

            if ($client) {
                return [
                    'first_response_minutes' => $client->first_response_minutes,
                    'resolution_minutes' => $client->resolution_minutes,
                    'source' => 'client',
                ];
            }
        }

        // 2) Global por tipo + criticidad.
        $global = SlaPolicy::query()
            ->where('type', $type->value)
            ->where('criticality', $criticality->value)
            ->first();

        if ($global) {
            return [
                'first_response_minutes' => $global->first_response_minutes,
                'resolution_minutes' => $global->resolution_minutes,
                'source' => 'global',
            ];
        }

        return ['first_response_minutes' => null, 'resolution_minutes' => null, 'source' => 'none'];
    }

    /**
     * Estado del SLA de primera respuesta de un ticket:
     *   - 'met'      ya respondido dentro del objetivo
     *   - 'breached' ya vencido (respondido tarde o aún sin responder pasado el objetivo)
     *   - 'warning'  aún no respondido pero consumido >= 75% del objetivo
     *   - 'ok'       aún no respondido, dentro de margen
     *   - 'none'     sin política aplicable
     */
    public function firstResponseStatus(Ticket $ticket): string
    {
        $target = $this->forTicket($ticket)['first_response_minutes'];
        if ($target === null) {
            return 'none';
        }

        if ($ticket->first_response_at !== null) {
            $mins = $ticket->created_at->diffInMinutes($ticket->first_response_at);

            return $mins <= $target ? 'met' : 'breached';
        }

        // Aún sin primera respuesta: se mide contra "ahora".
        $elapsed = $ticket->created_at->diffInMinutes(now());

        return $this->liveStatus($elapsed, $target);
    }

    /**
     * Estado del SLA de resolución de un ticket (mismos valores que arriba).
     */
    public function resolutionStatus(Ticket $ticket): string
    {
        $target = $this->forTicket($ticket)['resolution_minutes'];
        if ($target === null) {
            return 'none';
        }

        if ($ticket->resolved_at !== null) {
            $mins = $ticket->created_at->diffInMinutes($ticket->resolved_at);

            return $mins <= $target ? 'met' : 'breached';
        }

        $elapsed = $ticket->created_at->diffInMinutes(now());

        return $this->liveStatus($elapsed, $target);
    }

    /** Clasifica el estado "en vivo" (aún sin cumplir) según el % consumido. */
    private function liveStatus(int $elapsed, int $target): string
    {
        if ($elapsed > $target) {
            return 'breached';
        }
        if ($elapsed >= (int) ceil($target * 0.75)) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * Presentación de un estado de SLA: etiqueta corta y clases de badge
     * (semáforo). Pensado para la UI de tickets.
     *
     * @return array{label: string, classes: string}
     */
    public static function badge(string $status): array
    {
        return match ($status) {
            'met' => ['label' => 'Cumplido', 'classes' => 'bg-green-50 text-green-700'],
            'ok' => ['label' => 'En tiempo', 'classes' => 'bg-green-50 text-green-700'],
            'warning' => ['label' => 'Por vencer', 'classes' => 'bg-amber-50 text-amber-700'],
            'breached' => ['label' => 'Vencido', 'classes' => 'bg-red-50 text-red-700'],
            default => ['label' => 'Sin SLA', 'classes' => 'bg-slate-100 text-slate-500'],
        };
    }
}
