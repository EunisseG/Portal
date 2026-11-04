<?php

namespace Database\Seeders;

use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Models\SlaPolicy;
use Illuminate\Database\Seeder;

/**
 * Políticas de SLA por defecto (24/7), una por criticidad. Idempotente: no
 * pisa los valores que el admin haya ajustado (solo crea las que falten).
 * Los tiempos están en minutos.
 */
class SlaPoliciesSeeder extends Seeder
{
    public function run(): void
    {
        $byType = [
            // Tickets de CLIENTE.
            'client' => [
                TicketCriticality::Critical->value => [60, 240],      // 1 h / 4 h
                TicketCriticality::High->value => [240, 1440],        // 4 h / 24 h
                TicketCriticality::Medium->value => [480, 2880],      // 8 h / 48 h
                TicketCriticality::Low->value => [1440, 4320],        // 24 h / 72 h
            ],
            // Tickets INTERNOS (objetivos iniciales algo más holgados; editables).
            'internal' => [
                TicketCriticality::Critical->value => [120, 480],     // 2 h / 8 h
                TicketCriticality::High->value => [480, 2880],        // 8 h / 48 h
                TicketCriticality::Medium->value => [960, 4320],      // 16 h / 72 h
                TicketCriticality::Low->value => [2880, 7200],        // 48 h / 120 h
            ],
        ];

        foreach ($byType as $type => $defaults) {
            foreach ($defaults as $criticality => [$firstResponse, $resolution]) {
                SlaPolicy::firstOrCreate(
                    ['type' => $type, 'criticality' => $criticality],
                    [
                        'first_response_minutes' => $firstResponse,
                        'resolution_minutes' => $resolution,
                    ],
                );
            }
        }
    }
}
