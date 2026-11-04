<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Models\Addon;
use App\Livewire\Admin\Services\AddonIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddonIndexTest extends TestCase
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

    public function test_admin_can_list_all_addons(): void
    {
        Addon::factory()->create(['name' => 'IP dedicada visible']);

        $this->actingAs($this->admin())
            ->get(route('admin.addons.index'))
            ->assertOk()
            ->assertSee('Complementos')
            ->assertSee('IP dedicada visible');
    }

    public function test_status_filter_narrows_results(): void
    {
        Addon::factory()->create(['name' => 'Addon Activo', 'status' => AddonStatus::Active->value]);
        Addon::factory()->create(['name' => 'Addon Cancelado', 'status' => AddonStatus::Cancelled->value]);

        Livewire::actingAs($this->admin())
            ->test(AddonIndex::class)
            ->set('statusFilter', AddonStatus::Cancelled->value)
            ->assertSee('Addon Cancelado')
            ->assertDontSee('Addon Activo');
    }

    public function test_search_by_addon_name(): void
    {
        Addon::factory()->create(['name' => 'Backup diario premium']);
        Addon::factory()->create(['name' => 'Espacio extra']);

        Livewire::actingAs($this->admin())
            ->test(AddonIndex::class)
            ->set('search', 'Backup')
            ->assertSee('Backup diario premium')
            ->assertDontSee('Espacio extra');
    }

    public function test_billing_role_cannot_view_addons(): void
    {
        // billing no tiene addons.manage.
        $billing = User::factory()->create();
        $billing->assignRole(RoleName::Billing->value);

        $this->actingAs($billing)
            ->get(route('admin.addons.index'))
            ->assertForbidden();
    }
}
