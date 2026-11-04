<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Services\Enums\ProductType;
use App\Domain\Services\Models\Product;
use App\Domain\Services\Models\ProductCategory;
use App\Livewire\Admin\Services\ProductIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
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

    public function test_admin_can_create_a_product_with_type_and_category(): void
    {
        $category = ProductCategory::create(['title' => 'Hosting']);

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('openProductModal')
            ->set('productType', ProductType::Hosting->value)
            ->set('productCategoryId', $category->id)
            ->set('productName', 'Hosting_idea')
            ->set('billingMode', 'one_time')
            ->set('oneTimePrice', '350')
            ->call('saveProduct')
            ->assertSet('showProductModal', false);

        $this->assertDatabaseHas('products', [
            'name' => 'Hosting_idea',
            'type' => ProductType::Hosting->value,
            'category_id' => $category->id,
            'is_active' => true,
            'billing_mode' => 'one_time',
            'price_cents' => 35000,
        ]);
    }

    public function test_product_title_is_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('openProductModal')
            ->set('productName', '')
            ->call('saveProduct')
            ->assertHasErrors('productName');
    }

    public function test_admin_can_edit_a_product(): void
    {
        $product = Product::create(['name' => 'Viejo', 'type' => ProductType::Custom->value, 'billing_mode' => 'one_time', 'price_cents' => 10000, 'currency' => 'MXN', 'priority' => 1]);

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('editProduct', $product->id)
            ->set('productName', 'Nuevo nombre')
            ->set('oneTimePrice', '100')
            ->call('saveProduct');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Nuevo nombre']);
    }

    public function test_admin_can_delete_a_product(): void
    {
        $product = Product::create(['name' => 'Desechable', 'price_cents' => 0, 'currency' => 'MXN', 'priority' => 1]);

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('deleteProduct', $product->id);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_admin_can_update_priorities(): void
    {
        $a = Product::create(['name' => 'A', 'price_cents' => 0, 'currency' => 'MXN', 'priority' => 1]);
        $b = Product::create(['name' => 'B', 'price_cents' => 0, 'currency' => 'MXN', 'priority' => 2]);

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->set('priorities', [$a->id => 5, $b->id => 3])
            ->call('updatePriorities');

        $this->assertSame(5, $a->fresh()->priority);
        $this->assertSame(3, $b->fresh()->priority);
    }

    public function test_admin_can_create_edit_and_delete_a_category(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('openCategoryModal')
            ->set('categoryTitle', 'Correo')
            ->set('categoryIconUrl', 'https://x/icon.png')
            ->set('categoryDescription', 'Servicios de correo')
            ->call('saveCategory')
            ->assertSet('showCategoryModal', false);

        $category = ProductCategory::where('title', 'Correo')->firstOrFail();
        $this->assertSame('https://x/icon.png', $category->icon_url);

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('editCategory', $category->id)
            ->set('categoryTitle', 'Correo Pro')
            ->call('saveCategory');

        $this->assertDatabaseHas('product_categories', ['id' => $category->id, 'title' => 'Correo Pro']);

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('deleteCategory', $category->id);

        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
    }

    public function test_category_title_is_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('openCategoryModal')
            ->call('saveCategory')
            ->assertHasErrors('categoryTitle');
    }

    public function test_support_role_cannot_manage_products(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $this->actingAs($support)
            ->get(route('admin.products.index'))
            ->assertForbidden();
    }

    public function test_products_status_kpis_and_chart_have_distribution_data(): void
    {
        Product::create(['name' => 'Act 1', 'price_cents' => 0, 'currency' => 'MXN', 'priority' => 1, 'is_active' => true]);
        Product::create(['name' => 'Act 2', 'price_cents' => 0, 'currency' => 'MXN', 'priority' => 2, 'is_active' => true]);
        Product::create(['name' => 'Act 3', 'price_cents' => 0, 'currency' => 'MXN', 'priority' => 3, 'is_active' => true]);
        Product::create(['name' => 'Inact 1', 'price_cents' => 0, 'currency' => 'MXN', 'priority' => 4, 'is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->assertSee('Distribución de productos por estado')
            ->assertViewHas('kpis', fn ($kpis) => $kpis['active'] === 3
                && $kpis['inactive'] === 1
                && $kpis['total'] === 4)
            // chart['data'] = [habilitados, deshabilitados]
            ->assertViewHas('chart', fn ($chart) => $chart['data'] === [3, 1]
                && count($chart['labels']) === 2);
    }
}
