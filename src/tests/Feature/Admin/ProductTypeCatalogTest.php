<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Services\Models\Product;
use App\Domain\Services\Models\ProductTypeItem;
use App\Livewire\Admin\Services\ProductIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductTypeCatalogTest extends TestCase
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

    public function test_default_types_are_seeded(): void
    {
        $this->assertDatabaseHas('product_type_items', ['slug' => 'hosting']);
        $this->assertDatabaseHas('product_type_items', ['slug' => 'custom']);
    }

    public function test_admin_can_create_a_custom_type(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('setTab', 'types')
            ->call('openTypeModal')
            ->set('typeLabel', 'Plugin')
            ->call('saveType')
            ->assertSet('showTypeModal', false);

        $type = ProductTypeItem::where('label', 'Plugin')->firstOrFail();
        $this->assertSame('plugin', $type->slug);
        $this->assertTrue($type->is_active);
    }

    public function test_new_type_can_be_assigned_to_a_product(): void
    {
        $type = ProductTypeItem::create(['slug' => 'ssl', 'label' => 'Certificado SSL', 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('openProductModal')
            ->set('productName', 'SSL Wildcard')
            ->set('productType', 'ssl')
            ->set('billingMode', 'one_time')
            ->set('oneTimePrice', '1200')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::where('name', 'SSL Wildcard')->firstOrFail();
        $this->assertSame('ssl', $product->type);
        $this->assertSame('Certificado SSL', $product->typeLabel());
    }

    public function test_type_in_use_cannot_be_deleted(): void
    {
        $type = ProductTypeItem::create(['slug' => 'addon', 'label' => 'Addon', 'is_active' => true]);
        Product::create(['name' => 'X', 'type' => 'addon', 'billing_mode' => 'one_time', 'price_cents' => 0, 'currency' => 'MXN', 'priority' => 1]);

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('deleteType', $type->id);

        $this->assertDatabaseHas('product_type_items', ['id' => $type->id]);
    }

    public function test_unused_type_can_be_deleted(): void
    {
        $type = ProductTypeItem::create(['slug' => 'temporal', 'label' => 'Temporal', 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('deleteType', $type->id);

        $this->assertDatabaseMissing('product_type_items', ['id' => $type->id]);
    }

    public function test_product_requires_a_valid_type(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductIndex::class)
            ->call('openProductModal')
            ->set('productName', 'Sin tipo válido')
            ->set('productType', 'inexistente')
            ->set('billingMode', 'one_time')
            ->set('oneTimePrice', '100')
            ->call('saveProduct')
            ->assertHasErrors('productType');
    }
}
