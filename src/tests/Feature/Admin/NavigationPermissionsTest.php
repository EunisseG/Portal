<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Services\Enums\ProductType;
use App\Livewire\Admin\Reports\ReportIndex;
use App\Livewire\Admin\Services\ProductIndex;
use App\Livewire\Admin\System\BrandingSettings;
use App\Livewire\Admin\System\CurrencyIndex;
use App\Livewire\Admin\System\SurveyFormIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Menú por permisos, permisos propios por sección y tipo fijo de producto.
 */
class NavigationPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Usuario con un rol personalizado que solo tiene los permisos dados. */
    private function userWith(array $permissions): User
    {
        $role = Role::findOrCreate('rol_prueba_'.uniqid(), 'web');
        $role->syncPermissions($permissions);

        // Las pruebas de Livewire no pasan por el middleware de rutas: basta
        // con el rol personalizado para probar el permiso de cada pantalla.
        return $this->userWithRole($role->name);
    }

    public function test_support_menu_hides_sections_without_permission(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Support->value))
            ->get(route('admin.tickets.index'))
            ->assertOk()
            ->assertSee(route('admin.tickets.index'), false)
            ->assertSee(route('admin.surveys.index'), false)
            ->assertDontSee(route('admin.reports.index'), false)
            ->assertDontSee(route('admin.invoices.index'), false)
            ->assertDontSee(route('admin.settings.branding'), false)
            ->assertDontSee(route('admin.users.index'), false)
            // El grupo "Pagos" completo desaparece al no tener ningún ítem.
            ->assertDontSee(route('admin.currencies.index'), false);
    }

    public function test_billing_menu_shows_payments_and_reports_only(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Billing->value))
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee(route('admin.invoices.index'), false)
            ->assertSee(route('admin.reports.index'), false)
            ->assertDontSee(route('admin.tickets.index'), false)
            ->assertDontSee(route('admin.surveys.index'), false)
            ->assertDontSee(route('admin.products.index'), false);
    }

    public function test_admin_sees_every_section(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin->value))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.reports.index'), false)
            ->assertSee(route('admin.currencies.index'), false)
            ->assertSee(route('admin.surveys.index'), false)
            ->assertSee(route('admin.settings.branding'), false)
            ->assertSee(route('admin.roles.index'), false);
    }

    public function test_sections_use_their_own_permission(): void
    {
        // Los permisos anteriores ya no bastan...
        $old = $this->userWith(['integrations.manage', 'plans.manage', 'sla.manage']);
        Livewire::actingAs($old)->test(BrandingSettings::class)->assertForbidden();
        Livewire::actingAs($old)->test(CurrencyIndex::class)->assertForbidden();

        // ...y los nuevos sí.
        $new = $this->userWith(['settings.manage', 'currencies.manage', 'surveys.manage', 'reports.view']);
        Livewire::actingAs($new)->test(BrandingSettings::class)->assertOk();
        Livewire::actingAs($new)->test(CurrencyIndex::class)->assertOk();
        Livewire::actingAs($new)->test(SurveyFormIndex::class)->assertOk();
        Livewire::actingAs($new)->test(ReportIndex::class)->assertOk();
    }

    public function test_reports_need_reports_permission_not_invoices(): void
    {
        Livewire::actingAs($this->userWith(['invoices.view']))
            ->test(ReportIndex::class)
            ->assertForbidden();
    }

    public function test_products_work_as_fixed_options_without_types_tab(): void
    {
        $admin = $this->userWithRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(ProductIndex::class)
            ->call('setTab', 'types')
            ->assertSet('tab', 'products')
            ->call('openProductModal')
            ->assertSee('Funciona como')
            ->assertViewHas('typeOptions', fn ($options) => ! array_key_exists('hosting', $options)
                && $options === ProductType::manualOptions()
                && ($options['domain'] ?? null) === 'Dominio'
                && ($options['custom'] ?? null) === 'Otro');
    }
}
