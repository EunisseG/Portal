<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Sales\Enums\SalesEventType;
use App\Domain\Sales\Models\SalesEvent;
use App\Domain\Sales\Models\SalesRep;
use App\Domain\Sales\Services\SalesEventRecorder;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use App\Livewire\Admin\Reports\ReportIndex;
use App\Livewire\Admin\Sales\SalesRepIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesEventTest extends TestCase
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

    public function test_creating_a_service_records_a_new_sale_event(): void
    {
        $rep = SalesRep::factory()->create();
        $client = Client::factory()->create();

        // El ejecutivo vive en el SERVICIO.
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'sales_rep_id' => $rep->id,
            'total_cents' => 120000,
        ]);

        $this->assertDatabaseHas('sales_events', [
            'type' => SalesEventType::NewSale->value,
            'client_id' => $client->id,
            'sales_rep_id' => $rep->id,
            'subject_type' => $service->getMorphClass(),
            'subject_id' => $service->id,
            'amount_cents' => 120000,
        ]);
    }

    public function test_creating_an_addon_inherits_rep_from_its_service(): void
    {
        $rep = SalesRep::factory()->create();
        $client = Client::factory()->create();

        $service = Service::factory()->create([
            'client_id' => $client->id,
            'sales_rep_id' => $rep->id,
        ]);
        $addon = Addon::factory()->create(['service_id' => $service->id, 'price_cents' => 30000]);

        // El complemento hereda el ejecutivo de su servicio.
        $this->assertDatabaseHas('sales_events', [
            'type' => SalesEventType::NewSale->value,
            'subject_type' => $addon->getMorphClass(),
            'subject_id' => $addon->id,
            'amount_cents' => 30000,
            'sales_rep_id' => $rep->id,
        ]);
    }

    public function test_service_without_rep_records_event_with_null_rep(): void
    {
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'sales_rep_id' => null,
        ]);

        $this->assertDatabaseHas('sales_events', [
            'subject_type' => $service->getMorphClass(),
            'subject_id' => $service->id,
            'sales_rep_id' => null,
        ]);
    }

    public function test_new_sale_is_not_duplicated(): void
    {
        $client = Client::factory()->create();
        $service = Service::factory()->create(['client_id' => $client->id]);

        // Segundo intento manual: idempotente, no debe duplicar.
        app(SalesEventRecorder::class)->recordNewSale($service);

        $this->assertSame(1, SalesEvent::where('subject_type', $service->getMorphClass())
            ->where('subject_id', $service->id)
            ->where('type', SalesEventType::NewSale->value)
            ->count());
    }

    public function test_renewal_records_a_renewal_event_freezing_the_rep(): void
    {
        $rep = SalesRep::factory()->create();
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'sales_rep_id' => $rep->id,
        ]);
        $addon = Addon::factory()->create(['service_id' => $service->id, 'price_cents' => 50000]);

        app(SalesEventRecorder::class)->recordRenewal($addon);

        $this->assertDatabaseHas('sales_events', [
            'type' => SalesEventType::Renewal->value,
            'client_id' => $client->id,
            'sales_rep_id' => $rep->id,
            'subject_type' => $addon->getMorphClass(),
            'subject_id' => $addon->id,
            'amount_cents' => 50000,
        ]);
    }

    public function test_reports_show_sales_vs_renewals_by_rep(): void
    {
        $rep = SalesRep::factory()->create(['name' => 'Rep Ventas']);
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'sales_rep_id' => $rep->id,
            'total_cents' => 100000,
        ]);
        // Creación del servicio ya registró new_sale (100000). Añade una renovación.
        $addon = Addon::factory()->create(['service_id' => $service->id, 'price_cents' => 40000]);
        app(SalesEventRecorder::class)->recordRenewal($addon);

        Livewire::actingAs($this->admin())
            ->test(ReportIndex::class)
            ->assertSee('Ventas nuevas vs renovaciones por ejecutivo')
            ->assertViewHas('salesVsRenewals', function ($svr) {
                // new = servicio 100000 + addon 40000 = 140000 ; renovación = 40000
                return $svr['total'] === 180000
                    && in_array(1400.0, $svr['newSales'], true)
                    && in_array(400.0, $svr['renewals'], true);
            });
    }

    public function test_frozen_rep_stays_after_service_reassignment(): void
    {
        $repA = SalesRep::factory()->create();
        $repB = SalesRep::factory()->create();
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'sales_rep_id' => $repA->id,
        ]);

        // El evento quedó con repA. Reasignamos el SERVICIO a repB.
        $service->update(['sales_rep_id' => $repB->id]);

        // El histórico NO cambia: el evento sigue con repA.
        $this->assertDatabaseHas('sales_events', [
            'subject_type' => $service->getMorphClass(),
            'subject_id' => $service->id,
            'sales_rep_id' => $repA->id,
        ]);
    }

    public function test_admin_can_reassign_services_when_retiring_a_rep(): void
    {
        $repOld = SalesRep::factory()->create(['name' => 'Saliente']);
        $repNew = SalesRep::factory()->create(['name' => 'Entrante']);

        $client = Client::factory()->create();
        $s1 = Service::factory()->create(['client_id' => $client->id, 'sales_rep_id' => $repOld->id]);
        $s2 = Service::factory()->create(['client_id' => $client->id, 'sales_rep_id' => $repOld->id]);

        Livewire::actingAs($this->admin())
            ->test(SalesRepIndex::class)
            ->call('startReassign', $repOld->id)
            ->assertSet('reassigningId', $repOld->id)
            ->set('reassignTo', $repNew->id)
            ->set('selectedServices', [$s1->id, $s2->id])
            ->call('reassignSelected');

        $this->assertSame($repNew->id, $s1->fresh()->sales_rep_id);
        $this->assertSame($repNew->id, $s2->fresh()->sales_rep_id);
    }

    public function test_reassign_requires_a_different_destination(): void
    {
        $rep = SalesRep::factory()->create();
        $client = Client::factory()->create();
        $service = Service::factory()->create(['client_id' => $client->id, 'sales_rep_id' => $rep->id]);

        Livewire::actingAs($this->admin())
            ->test(SalesRepIndex::class)
            ->call('startReassign', $rep->id)
            ->set('reassignTo', $rep->id)   // mismo => inválido
            ->set('selectedServices', [$service->id])
            ->call('reassignSelected')
            ->assertHasErrors('reassignTo');
    }
}
