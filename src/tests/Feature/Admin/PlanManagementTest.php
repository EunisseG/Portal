<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Plan;
use App\Livewire\Admin\Plans\PlanForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanManagementTest extends TestCase
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

    public function test_admin_can_create_a_plan_with_prices_per_cycle(): void
    {
        Livewire::actingAs($this->admin())
            ->test(PlanForm::class)
            ->set('name', 'Hosting Test')
            ->set('type', ServiceType::Hosting->value)
            ->set('enabled.monthly', true)
            ->set('unitPrice.monthly', '100')
            ->set('taxRate.monthly', '16')
            ->set('enabled.annual', true)
            ->set('unitPrice.annual', '1000')
            ->set('taxRate.annual', '16')
            ->call('save')
            ->assertRedirect(route('admin.plans.index'));

        $plan = Plan::where('name', 'Hosting Test')->firstOrFail();
        $this->assertCount(2, $plan->prices);

        $monthly = $plan->prices->firstWhere('billing_cycle', BillingCycle::Monthly);
        $this->assertSame(10000, $monthly->unit_price_cents);
        // total = 100 + 16% = 116.00 => 11600 centavos
        $this->assertSame(11600, $monthly->totalCents());

        $this->assertDatabaseHas('audits', ['action' => AuditAction::PlanCreated->value]);
    }

    public function test_plan_requires_at_least_one_enabled_cycle(): void
    {
        Livewire::actingAs($this->admin())
            ->test(PlanForm::class)
            ->set('name', 'Sin ciclos')
            ->set('type', ServiceType::Hosting->value)
            ->call('save')
            ->assertHasErrors('enabled');
    }

    public function test_enabled_cycle_requires_a_price(): void
    {
        Livewire::actingAs($this->admin())
            ->test(PlanForm::class)
            ->set('name', 'Ciclo sin precio')
            ->set('type', ServiceType::Hosting->value)
            ->set('enabled.monthly', true)
            ->set('unitPrice.monthly', '')
            ->call('save')
            ->assertHasErrors('unitPrice.monthly');
    }
}
