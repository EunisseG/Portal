<?php

namespace Database\Seeders;

use App\Domain\Clients\Models\Client;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use Illuminate\Database\Seeder;

/**
 * Tickets de ejemplo (desarrollo). Cada uno con su primer mensaje del cliente
 * y, en algunos, una respuesta del staff.
 */
class TicketsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $clients = collect();
        $demo = Client::whereHas('user', fn ($q) => $q->where('email', 'cliente@wortev.test'))->first();
        if ($demo) {
            $clients->push($demo);
        }
        $clients = $clients->merge(Client::query()->inRandomOrder()->limit(4)->get())->unique('id');

        foreach ($clients as $client) {
            // Ticket abierto por el cliente.
            $open = Ticket::factory()->create([
                'client_id' => $client->id,
                'subject' => 'Consulta sobre mi servicio',
                'priority' => TicketPriority::Normal->value,
                'status' => TicketStatus::Open->value,
            ]);
            TicketMessage::factory()->create([
                'ticket_id' => $open->id,
                'user_id' => $client->user_id,
                'is_staff' => false,
                'body' => 'Hola, tengo una duda sobre mi servicio de hosting. ¿Podrían ayudarme?',
            ]);

            // Ticket ya respondido por el staff.
            $answered = Ticket::factory()->create([
                'client_id' => $client->id,
                'subject' => 'Problema al acceder al panel',
                'priority' => TicketPriority::High->value,
                'status' => TicketStatus::Answered->value,
            ]);
            TicketMessage::factory()->create([
                'ticket_id' => $answered->id,
                'user_id' => $client->user_id,
                'is_staff' => false,
                'body' => 'No puedo acceder al panel desde ayer.',
            ]);
            TicketMessage::factory()->create([
                'ticket_id' => $answered->id,
                'user_id' => null,
                'is_staff' => true,
                'body' => 'Hola, ya revisamos tu cuenta y restablecimos el acceso. Inténtalo de nuevo, por favor.',
            ]);
        }
    }
}
