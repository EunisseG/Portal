<?php

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
use App\Domain\Tickets\Enums\TicketDepartment;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'TK-2026-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'client_id' => Client::factory(),
            'subject' => fake()->sentence(4),
            'department' => TicketDepartment::Support->value,
            'priority' => TicketPriority::Normal->value,
            'status' => TicketStatus::Open->value,
            'last_reply_at' => now(),
        ];
    }
}
