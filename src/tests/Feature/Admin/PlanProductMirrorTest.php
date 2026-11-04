<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Services\Enums\ProductType;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Plan;
use App\Domain\Services\Models\Product;
use App\Domain\Services\Services\PlanService;
use App\Livewire\Admin\Services\ProductIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanProductMirrorTest extends TestCase
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

    private function createPlan(): Plan
    {
        return app(PlanService::class)->create(
            ['name' => 'Hosting Idea', 'type' => ServiceType::Hosting->value, 'is_active' => true],
            [
                'monthly' => ['unit_price' => '199', 'tax_rate' => '16'],
                'annual' => ['unit_price' => '1999', 'tax_rate' => '16'],
            ],
        );
    }

    public function test_creating_a_plan_creates_a_mirror_product_with_monthly_price(): void
    {
        $plan = $this->createPlan();

        $product = Product::where('plan_id', $plan->id)->first();
        $this->assertNotNull($product);
        $this->assertSame('Hosting Idea', $product->name);
        $this->assertSame('hosting', $product->type);
        $this->assertTrue($product->is_active);
        // Precio del ciclo más común (mensual): 199.00 => 19900 centavos.
        $this->assertSame(19900, $product->price_cents);
        $this->assertTrue($product->isFromPlan());
    }

    public function test_updating_a_plan_updates_its_mirror_product(): void
    {
        $plan = $this->createPlan();

        app(PlanService::class)->update(
            $plan,
            ['name' => 'Hosting Idea Plus', 'type' => ServiceType::Hosting->value, 'is_active' => false],
            ['monthly' => ['unit_price' => '249', 'tax_rate' => '16']],
        );

        $product = Product::where('plan_id', $plan->id)->firstOrFail();
        $this->assertSame('Hosting Idea Plus', $product->name);
        $this->assertSame(24900, $product->price_cents);
        $this->assertFalse($product->is_active);
    }

    public function test_deleting_a_plan_removes_its_mirror_product(): void
    {
        $plan = $this->createPlan();
        $this->assertDatabaseHas('products', ['plan_id' => $plan->id]);

        app(PlanService::class)->delete($plan);

        $this->assertDatabaseMissing('products', ['plan_id' => $plan->id]);
    }

    public function test_mirror_product_cannot_be_edited_or_deleted_manually(): void
    {
        $plan = $this->createPlan();
        $product = Product::where('plan_id', $plan->id)->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('editProduct', $product->id)
            ->assertForbidden();

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('deleteProduct', $product->id)
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_manual_products_still_work(): void
    {
        // Un producto creado a mano (sin plan) sigue siendo editable/borrable.
        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('openProductModal')
            ->set('productType', ProductType::Custom->value)
            ->set('productName', 'Producto Manual')
            ->set('billingMode', 'one_time')
            ->set('oneTimePrice', '250')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Producto Manual')->firstOrFail();
        $this->assertNull($product->plan_id);
        $this->assertFalse($product->isFromPlan());
    }
}
