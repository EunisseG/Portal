<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Clients\Models\Client;
use App\Domain\Sales\Models\SalesRep;
use App\Domain\Services\Models\Service;
use App\Livewire\Admin\Services\ServiceForm;
use App\Livewire\Admin\Reports\ReportIndex;
use App\Livewire\Admin\Sales\SalesRepIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class SalesRepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_admin_can_create_edit_and_delete_a_sales_rep(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SalesRepIndex::class)
            ->set('name', 'Laura Ventas')
            ->set('email', 'laura@wortev.com')
            ->set('phone', '55 1111 2222')
            ->call('save')
            ->assertHasNoErrors();

        $rep = SalesRep::where('name', 'Laura Ventas')->firstOrFail();
        $this->assertSame('laura@wortev.com', $rep->email);
        $this->assertTrue($rep->is_active);

        Livewire::actingAs($this->admin())
            ->test(SalesRepIndex::class)
            ->call('edit', $rep->id)
            ->set('name', 'Laura G.')
            ->call('save');

        $this->assertSame('Laura G.', $rep->fresh()->name);

        Livewire::actingAs($this->admin())
            ->test(SalesRepIndex::class)
            ->call('delete', $rep->id);

        $this->assertDatabaseMissing('sales_reps', ['id' => $rep->id]);
    }

    public function test_sales_rep_with_services_cannot_be_deleted(): void
    {
        $rep = SalesRep::factory()->create();
        $client = Client::factory()->create();
        Service::factory()->create(['client_id' => $client->id, 'sales_rep_id' => $rep->id]);

        Livewire::actingAs($this->admin())
            ->test(SalesRepIndex::class)
            ->call('delete', $rep->id);

        // Sigue existiendo (se bloqueó por tener servicios).
        $this->assertDatabaseHas('sales_reps', ['id' => $rep->id]);
    }

    public function test_name_is_required_for_a_sales_rep(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SalesRepIndex::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_support_role_cannot_manage_sales_reps(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        Livewire::actingAs($support)
            ->test(SalesRepIndex::class)
            ->assertForbidden();
    }

    public function test_admin_can_assign_a_sales_rep_when_editing_a_service(): void
    {
        $rep = SalesRep::factory()->create(['name' => 'Pedro Cuenta']);
        $client = Client::factory()->create();
        // Tipo no-hosting para que guardar no exija el bloque de dominio.
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'sales_rep_id' => null,
            'type' => \App\Domain\Services\Enums\ServiceType::Email->value,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class, ['service' => $service])
            ->assertSet('sales_rep_id', null)
            ->set('sales_rep_id', $rep->id)
            ->call('save')
            ->assertRedirect(route('admin.services.index'));

        $this->assertSame($rep->id, $service->fresh()->sales_rep_id);
    }

    public function test_client_dashboard_shows_assigned_sales_rep(): void
    {
        $rep = SalesRep::factory()->create(['name' => 'Ana Ejecutiva', 'email' => 'ana@wortev.com']);
        $owner = User::factory()->create();
        $owner->assignRole(RoleName::Client->value);
        $client = Client::factory()->create(['user_id' => $owner->id]);
        // El ejecutivo se asigna a un servicio del cliente.
        Service::factory()->create(['client_id' => $client->id, 'sales_rep_id' => $rep->id]);

        $this->actingAs($owner->fresh())
            ->get('/client')
            ->assertOk()
            ->assertSee('Tu ejecutivo de ventas')
            ->assertSee('Ana Ejecutiva');
    }

    public function test_client_dashboard_hides_section_without_sales_rep(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(RoleName::Client->value);
        $client = Client::factory()->create(['user_id' => $owner->id]);
        // Servicio sin ejecutivo asignado.
        Service::factory()->create(['client_id' => $client->id, 'sales_rep_id' => null]);

        $this->actingAs($owner->fresh())
            ->get('/client')
            ->assertOk()
            ->assertDontSee('Tu ejecutivo de ventas');
    }

    public function test_reports_show_sales_by_rep_charts(): void
    {
        $repA = SalesRep::factory()->create(['name' => 'Rep A']);
        $repB = SalesRep::factory()->create(['name' => 'Rep B']);

        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        // El ejecutivo vive en el servicio; cada cliente tiene un servicio con
        // su ejecutivo. La factura del cobro se liga al servicio de Rep A.
        $serviceA = Service::factory()->create(['client_id' => $clientA->id, 'sales_rep_id' => $repA->id]);
        Service::factory()->create(['client_id' => $clientB->id, 'sales_rep_id' => $repB->id]);

        // Un pago de 1000.00 para el servicio de Rep A.
        $invoice = Invoice::factory()->create([
            'client_id' => $clientA->id,
            'service_id' => $serviceA->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 100000,
            'currency' => 'MXN',
            'method' => 'transfer',
            'paid_at' => Carbon::now()->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ReportIndex::class)
            ->assertSee('Ingresos por ejecutivo de ventas')
            ->assertSee('Clientes por ejecutivo')
            // clientsByRep ahora cuenta SERVICIOS por ejecutivo => 2 servicios.
            ->assertViewHas('clientsByRep', fn ($c) => $c['total'] === 2)
            // revenueByRep: 100000 centavos cobrados en total (vía servicio).
            ->assertViewHas('revenueByRep', fn ($r) => $r['total'] === 100000);
    }
}
