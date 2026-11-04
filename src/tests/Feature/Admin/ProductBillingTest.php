<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\BillingMode;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Plan;
use App\Domain\Services\Models\Product;
use App\Domain\Services\Services\PlanService;
use App\Livewire\Admin\Clients\ClientForm;
use App\Livewire\Admin\Services\ProductIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductBillingTest extends TestCase
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

    public function test_create_one_time_product(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('openProductModal')
            ->set('productName', 'Instalación única')
            ->set('productType', 'custom')
            ->set('billingMode', 'one_time')
            ->set('oneTimePrice', '500')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Instalación única')->firstOrFail();
        $this->assertSame(BillingMode::OneTime, $product->billing_mode);
        $this->assertSame(50000, $product->price_cents);
        $this->assertCount(0, $product->prices);
    }

    public function test_create_recurring_product_with_cycles(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('openProductModal')
            ->set('productName', 'Hosting Mensual/Anual')
            ->set('productType', 'hosting')
            ->set('billingMode', 'recurring')
            ->set('enabled.monthly', true)
            ->set('unitPrice.monthly', '199')
            ->set('taxRate.monthly', '16')
            ->set('enabled.annual', true)
            ->set('unitPrice.annual', '1999')
            ->set('taxRate.annual', '16')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Hosting Mensual/Anual')->with('prices')->firstOrFail();
        $this->assertSame(BillingMode::Recurring, $product->billing_mode);
        $this->assertCount(2, $product->prices);

        $monthly = $product->prices->firstWhere('billing_cycle', BillingCycle::Monthly);
        $this->assertSame(19900, $monthly->unit_price_cents);
        $this->assertSame(23084, $monthly->totalCents()); // 199 + 16%
    }

    public function test_recurring_product_requires_at_least_one_cycle(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('openProductModal')
            ->set('productName', 'Sin ciclos')
            ->set('billingMode', 'recurring')
            ->call('saveProduct')
            ->assertHasErrors('enabled');

        $this->assertSame(0, Product::where('name', 'Sin ciclos')->count());
    }

    public function test_plan_mirror_product_is_recurring_and_copies_cycles(): void
    {
        $plan = app(PlanService::class)->create(
            ['name' => 'Hosting Idea', 'type' => ServiceType::Hosting->value, 'is_active' => true],
            [
                'monthly' => ['unit_price' => '199', 'tax_rate' => '16'],
                'annual' => ['unit_price' => '1999', 'tax_rate' => '16'],
            ],
        );

        $product = Product::where('plan_id', $plan->id)->with('prices')->firstOrFail();
        $this->assertSame(BillingMode::Recurring, $product->billing_mode);
        $this->assertCount(2, $product->prices);
        $this->assertNotNull($product->prices->firstWhere('billing_cycle', BillingCycle::Annual));
    }

    public function test_initial_service_uses_chosen_cycle(): void
    {
        $plan = app(PlanService::class)->create(
            ['name' => 'Hosting Idea', 'type' => ServiceType::Hosting->value, 'is_active' => true],
            [
                'monthly' => ['unit_price' => '199', 'tax_rate' => '16'],
                'annual' => ['unit_price' => '1999', 'tax_rate' => '16'],
            ],
        );
        $product = Product::where('plan_id', $plan->id)->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(ClientForm::class)
            ->set('name', 'Cliente Ciclo')
            ->set('email', 'ciclo@wortev.test')
            ->set('addProduct', true)
            ->set('productId', $product->id)
            ->set('productCycle', 'annual')
            ->call('save');

        $client = Client::whereHas('user', fn ($q) => $q->where('email', 'ciclo@wortev.test'))->firstOrFail();
        $service = $client->services()->firstOrFail();

        // Precio anual: 1999 + 16% = 2318.84 => 231884 centavos.
        $this->assertSame(BillingCycle::Annual, $service->billing_cycle);
        $this->assertSame(231884, $service->total_cents);
    }
}
