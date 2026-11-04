<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Clients\Models\Client;
use App\Domain\Reporting\Services\AdminDashboardStats;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Admin Prueba']);
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_cards_count_clients_orders_invoices_and_subcounts(): void
    {
        $client = Client::factory()->create();
        Service::factory()->create(['client_id' => $client->id, 'status' => ServiceStatus::Active->value]);
        Service::factory()->create(['client_id' => $client->id, 'status' => ServiceStatus::Suspended->value]);
        Service::factory()->create(['client_id' => $client->id, 'status' => ServiceStatus::Suspended->value]);

        Invoice::factory()->create(['client_id' => $client->id, 'status' => 'pending']);
        Invoice::factory()->create(['client_id' => $client->id, 'status' => 'overdue']);
        Invoice::factory()->paid()->create(['client_id' => $client->id]);

        $cards = app(AdminDashboardStats::class)->cards();

        $this->assertSame(1, $cards['clients']['total']);
        $this->assertSame(3, $cards['orders']['total']);
        $this->assertSame(2, $cards['orders']['suspended']);
        $this->assertSame(3, $cards['invoices']['total']);
        $this->assertSame(2, $cards['invoices']['unpaid']); // pending + overdue, no la paid
    }

    public function test_income_statistics_only_sum_paid_invoices(): void
    {
        $client = Client::factory()->create();

        // Pagada dentro de este mes: sí cuenta.
        Invoice::factory()->paid()->create([
            'client_id' => $client->id,
            'total_cents' => 120000,
            'issued_at' => now()->startOfMonth()->addDay()->toDateString(),
        ]);
        // Pendiente dentro de este mes: NO cuenta para ingresos.
        Invoice::factory()->create([
            'client_id' => $client->id,
            'total_cents' => 999999,
            'status' => 'pending',
            'issued_at' => now()->startOfMonth()->addDay()->toDateString(),
        ]);

        $stats = app(AdminDashboardStats::class)->statistics();

        // 120000 centavos => 1,200.00 MXN, solo la factura pagada.
        $this->assertSame('1,200.00 MXN', $stats['income']['this_month']);
        $this->assertSame('1,200.00 MXN', $stats['income']['total']);
    }

    public function test_dashboard_renders_with_real_data_for_admin(): void
    {
        $client = Client::factory()->create();
        Service::factory()->create(['client_id' => $client->id, 'name' => 'Hosting Pro']);
        Invoice::factory()->create(['client_id' => $client->id, 'folio' => 'WV-TEST-1']);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Escritorio')
            ->assertSee('Estadísticas')
            ->assertSee('Facturas recientes')
            ->assertSee('Últimos pedidos')
            ->assertSee('Ventas por producto')
            ->assertSee('WV-TEST-1')
            ->assertSee('Hosting Pro')
            ->assertSee('Tickets de clientes por estado')
            ->assertSee('Tickets internos por estado')
            ->assertSee('Tickets creados')
            ->assertSee('Satisfacción del cliente')
            ->assertSee('Respondidas')
            ->assertSee('Ver detalle');
    }

    public function test_tickets_by_status_counts_each_state(): void
    {
        $client = Client::factory()->create();
        \App\Domain\Tickets\Models\Ticket::factory()->count(2)->create([
            'client_id' => $client->id,
            'status' => \App\Domain\Tickets\Enums\TicketStatus::Open->value,
        ]);
        \App\Domain\Tickets\Models\Ticket::factory()->create([
            'client_id' => $client->id,
            'status' => \App\Domain\Tickets\Enums\TicketStatus::Closed->value,
        ]);

        $data = app(AdminDashboardStats::class)->ticketsByStatus();

        // Orden de estados: Open, Pending, Answered, Resolved, Closed.
        $this->assertSame(['Abierto', 'En espera', 'Respondido', 'Resuelto', 'Cerrado'], $data['labels']);
        $this->assertSame([2, 0, 0, 0, 1], $data['data']);
        $this->assertCount(5, $data['colors']);
    }

    public function test_tickets_by_status_can_be_filtered_by_type(): void
    {
        $client = Client::factory()->create();
        \App\Domain\Tickets\Models\Ticket::factory()->count(2)->create([
            'client_id' => $client->id,
            'type' => \App\Domain\Tickets\Enums\TicketType::Client->value,
            'status' => \App\Domain\Tickets\Enums\TicketStatus::Open->value,
        ]);
        \App\Domain\Tickets\Models\Ticket::factory()->create([
            'client_id' => null,
            'type' => \App\Domain\Tickets\Enums\TicketType::Internal->value,
            'status' => \App\Domain\Tickets\Enums\TicketStatus::Pending->value,
        ]);

        $stats = app(AdminDashboardStats::class);

        $clientData = $stats->ticketsByStatus(\App\Domain\Tickets\Enums\TicketType::Client);
        $internalData = $stats->ticketsByStatus(\App\Domain\Tickets\Enums\TicketType::Internal);

        // Clientes: 2 abiertos, nada más.
        $this->assertSame([2, 0, 0, 0, 0], $clientData['data']);
        // Internos: 1 en espera.
        $this->assertSame([0, 1, 0, 0, 0], $internalData['data']);
    }

    public function test_tickets_by_day_returns_one_entry_per_day(): void
    {
        $client = Client::factory()->create();
        \App\Domain\Tickets\Models\Ticket::factory()->create([
            'client_id' => $client->id,
            'created_at' => now(),
        ]);

        $data = app(AdminDashboardStats::class)->ticketsByDay(30);

        $this->assertCount(30, $data['labels']);
        $this->assertCount(30, $data['data']);
        // El último día (hoy) tiene 1 ticket.
        $this->assertSame(1, $data['data'][29]);
    }
}
