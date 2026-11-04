<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\BillingMode;
use App\Domain\Services\Models\AddonCatalogItem;
use App\Livewire\Admin\Services\AddonCatalogIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddonCatalogBillingTest extends TestCase
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

    public function test_create_one_time_catalog_addon(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AddonCatalogIndex::class)
            ->set('name', 'IP dedicada')
            ->set('billingMode', 'one_time')
            ->set('oneTimePrice', '500')
            ->call('save')
            ->assertHasNoErrors();

        $item = AddonCatalogItem::where('name', 'IP dedicada')->firstOrFail();
        $this->assertSame(BillingMode::OneTime, $item->billing_mode);
        $this->assertSame(50000, $item->price_cents);
        $this->assertCount(0, $item->prices);
    }

    public function test_create_recurring_catalog_addon_with_cycles(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AddonCatalogIndex::class)
            ->set('name', 'Respaldo diario')
            ->set('billingMode', 'recurring')
            ->set('enabled.monthly', true)
            ->set('unitPrice.monthly', '99')
            ->set('taxRate.monthly', '16')
            ->set('enabled.annual', true)
            ->set('unitPrice.annual', '999')
            ->set('taxRate.annual', '16')
            ->call('save')
            ->assertHasNoErrors();

        $item = AddonCatalogItem::where('name', 'Respaldo diario')->with('prices')->firstOrFail();
        $this->assertSame(BillingMode::Recurring, $item->billing_mode);
        $this->assertCount(2, $item->prices);

        $monthly = $item->prices->firstWhere('billing_cycle', BillingCycle::Monthly);
        $this->assertSame(9900, $monthly->unit_price_cents);
        $this->assertSame(11484, $monthly->totalCents()); // 99 + 16%
    }

    public function test_recurring_catalog_addon_requires_at_least_one_cycle(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AddonCatalogIndex::class)
            ->set('name', 'Sin ciclos')
            ->set('billingMode', 'recurring')
            ->call('save')
            ->assertHasErrors('enabled');

        $this->assertSame(0, AddonCatalogItem::where('name', 'Sin ciclos')->count());
    }

    public function test_one_time_catalog_addon_requires_valid_price(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AddonCatalogIndex::class)
            ->set('name', 'Sin precio')
            ->set('billingMode', 'one_time')
            ->set('oneTimePrice', '')
            ->call('save')
            ->assertHasErrors('oneTimePrice');

        $this->assertSame(0, AddonCatalogItem::where('name', 'Sin precio')->count());
    }

    public function test_editing_recurring_addon_preloads_cycles(): void
    {
        $item = AddonCatalogItem::create([
            'name' => 'Editable',
            'billing_mode' => BillingMode::Recurring->value,
            'price_cents' => 9900,
            'currency' => 'MXN',
            'is_active' => true,
        ]);
        $item->prices()->create([
            'billing_cycle' => BillingCycle::Monthly->value,
            'unit_price_cents' => 9900,
            'tax_rate' => 16,
            'currency' => 'MXN',
        ]);

        Livewire::actingAs($this->admin())
            ->test(AddonCatalogIndex::class)
            ->call('edit', $item->id)
            ->assertSet('billingMode', 'recurring')
            ->assertSet('enabled.monthly', true)
            ->assertSet('unitPrice.monthly', '99.00');
    }

    public function test_tabs_show_catalog_and_deployed(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AddonCatalogIndex::class)
            ->assertSet('tab', 'catalog')
            ->assertSee('Catálogo')
            ->assertSee('Desplegados')
            ->call('setTab', 'deployed')
            ->assertSet('tab', 'deployed');
    }
}
