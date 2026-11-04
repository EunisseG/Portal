<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Services\Enums\DiscountType;
use App\Domain\Services\Models\Promotion;
use App\Livewire\Admin\Services\PromotionIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromotionTest extends TestCase
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

    public function test_admin_can_create_a_percent_promotion(): void
    {
        Livewire::actingAs($this->admin())
            ->test(PromotionIndex::class)
            ->set('code', 'bienvenida10')
            ->set('name', 'Descuento de bienvenida')
            ->set('discount_type', DiscountType::Percent->value)
            ->set('discount_value', 10)
            ->call('save');

        $this->assertDatabaseHas('promotions', [
            'code' => 'BIENVENIDA10',
            'discount_type' => 'percent',
            'discount_value' => 10,
        ]);
    }

    public function test_code_must_be_unique(): void
    {
        Promotion::create(['code' => 'PROMO', 'name' => 'X', 'discount_type' => 'percent', 'discount_value' => 5]);

        Livewire::actingAs($this->admin())
            ->test(PromotionIndex::class)
            ->set('code', 'PROMO')
            ->set('name', 'Otra')
            ->set('discount_value', 5)
            ->call('save')
            ->assertHasErrors('code');
    }

    public function test_end_date_must_be_after_or_equal_start(): void
    {
        Livewire::actingAs($this->admin())
            ->test(PromotionIndex::class)
            ->set('code', 'RANGO')
            ->set('name', 'Rango inválido')
            ->set('discount_value', 5)
            ->set('starts_at', '2026-05-10')
            ->set('ends_at', '2026-05-01')
            ->call('save')
            ->assertHasErrors('ends_at');
    }

    public function test_admin_can_edit_a_promotion(): void
    {
        $promo = Promotion::create(['code' => 'EDIT', 'name' => 'Viejo', 'discount_type' => 'fixed', 'discount_value' => 5000]);

        Livewire::actingAs($this->admin())
            ->test(PromotionIndex::class)
            ->call('edit', $promo->id)
            ->set('name', 'Nuevo nombre')
            ->call('save');

        $this->assertDatabaseHas('promotions', ['id' => $promo->id, 'name' => 'Nuevo nombre']);
    }

    public function test_admin_can_delete_a_promotion(): void
    {
        $promo = Promotion::create(['code' => 'DEL', 'name' => 'Borrar', 'discount_type' => 'percent', 'discount_value' => 5]);

        Livewire::actingAs($this->admin())
            ->test(PromotionIndex::class)
            ->call('delete', $promo->id);

        $this->assertDatabaseMissing('promotions', ['id' => $promo->id]);
    }

    public function test_support_role_cannot_manage_promotions(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $this->actingAs($support)
            ->get(route('admin.promotions.index'))
            ->assertForbidden();
    }
}
