<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\System\Models\Currency;
use App\Livewire\Admin\System\CurrencyIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurrencyTest extends TestCase
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

    public function test_admin_can_create_a_currency(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CurrencyIndex::class)
            ->set('code', 'usd')
            ->set('name', 'Dólar estadounidense')
            ->set('symbol', 'US$')
            ->call('save');

        // El código se guarda en mayúsculas.
        $this->assertDatabaseHas('currencies', ['code' => 'USD', 'name' => 'Dólar estadounidense']);
    }

    public function test_code_must_be_unique(): void
    {
        Currency::create(['code' => 'MXN', 'name' => 'Peso', 'symbol' => '$']);

        Livewire::actingAs($this->admin())
            ->test(CurrencyIndex::class)
            ->set('code', 'MXN')
            ->set('name', 'Otro')
            ->set('symbol', '$')
            ->call('save')
            ->assertHasErrors('code');
    }

    public function test_code_must_be_three_letters(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CurrencyIndex::class)
            ->set('code', 'EU')
            ->set('name', 'Euro')
            ->set('symbol', '€')
            ->call('save')
            ->assertHasErrors('code');
    }

    public function test_admin_can_edit_a_currency(): void
    {
        $currency = Currency::create(['code' => 'MXN', 'name' => 'Peso', 'symbol' => '$']);

        Livewire::actingAs($this->admin())
            ->test(CurrencyIndex::class)
            ->call('edit', $currency->id)
            ->set('name', 'Peso mexicano')
            ->call('save');

        $this->assertDatabaseHas('currencies', ['id' => $currency->id, 'name' => 'Peso mexicano']);
    }

    public function test_admin_can_delete_a_currency(): void
    {
        $currency = Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€']);

        Livewire::actingAs($this->admin())
            ->test(CurrencyIndex::class)
            ->call('delete', $currency->id);

        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
    }

    public function test_support_role_cannot_manage_currencies(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $this->actingAs($support)
            ->get(route('admin.currencies.index'))
            ->assertForbidden();
    }
}
