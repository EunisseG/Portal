<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Plan;
use App\Domain\Services\Models\PlanPrice;
use App\Domain\Services\Models\Service;
use App\Livewire\Admin\Services\ServiceForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServicePlanIntegrationTest extends TestCase
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

    public function test_selecting_plan_and_cycle_autofills_price(): void
    {
        $client = Client::factory()->create();
        $plan = Plan::factory()->create([
            'type' => ServiceType::Hosting->value,
            'is_active' => true,
        ]);
        PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Annual->value,
            'unit_price_cents' => 250000, // $2,500.00
            'tax_rate' => 16,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('type', ServiceType::Hosting->value)
            ->set('plan_id', (string) $plan->id)
            ->set('billing_cycle', BillingCycle::Annual->value)
            ->assertSet('unit_price', '2500.00')
            ->assertSet('tax_rate', '16');
    }

    public function test_expiry_is_calculated_from_start_and_cycle(): void
    {
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('billing_cycle', BillingCycle::Annual->value)
            ->set('starts_at', '2026-01-15')
            ->assertSet('expires_at', '2027-01-15');
    }

    public function test_service_saves_price_breakdown_with_plan(): void
    {
        $client = Client::factory()->create();
        $plan = Plan::factory()->create(['type' => ServiceType::Hosting->value, 'is_active' => true]);
        PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'unit_price_cents' => 20000, // $200
            'tax_rate' => 16,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('type', ServiceType::Hosting->value)
            ->set('plan_id', (string) $plan->id)
            ->set('billing_cycle', BillingCycle::Monthly->value)
            ->set('name', 'Servicio con plan')
            // Hosting requiere dominio: uno nuevo que el cliente ya tiene (sin costo).
            ->set('domainMode', 'new')
            ->set('domainName', 'conplan.com')
            ->set('domainOrigin', 'owned')
            ->call('save')
            ->assertRedirect(route('admin.services.index'));

        $service = Service::where('name', 'Servicio con plan')->firstOrFail();
        $this->assertSame($plan->id, $service->plan_id);
        $this->assertSame(20000, $service->unit_price_cents);
        $this->assertSame(23200, $service->total_cents); // 200 + 16%
    }
}
