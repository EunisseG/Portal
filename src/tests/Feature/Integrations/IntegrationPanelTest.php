<?php

namespace Tests\Feature\Integrations;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Integrations\Registry\IntegrationCapability;
use App\Livewire\Admin\Integrations\IntegrationCatalog;
use App\Livewire\Admin\Integrations\IntegrationIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationPanelTest extends TestCase
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

    private function integration(bool $enabled = false): Integration
    {
        return Integration::create([
            'key' => 'monday',
            'name' => 'Monday.com',
            'capability' => IntegrationCapability::Tickets->value,
            'is_enabled' => $enabled,
            'status' => $enabled ? 'connected' : 'not_configured',
        ]);
    }

    public function test_admin_can_view_the_catalog(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.integrations.catalog'))
            ->assertOk()
            ->assertSee('Catálogo de integraciones')
            ->assertSee('Monday.com')
            ->assertSee('API personalizada')
            // WHM se configura por servidor; no aparece en el catálogo.
            ->assertDontSee('WHM / cPanel');
    }

    public function test_installed_whm_is_hidden_from_the_list(): void
    {
        // Aunque exista una integración WHM instalada, no debe listarse
        // (se configura por servidor).
        Integration::create([
            'key' => 'whm',
            'name' => 'WHM / cPanel',
            'capability' => IntegrationCapability::Hosting->value,
            'is_enabled' => true,
            'status' => 'connected',
        ]);
        $this->integration(); // monday visible

        $this->actingAs($this->admin())
            ->get(route('admin.integrations.index'))
            ->assertOk()
            ->assertSee('Monday.com')
            ->assertDontSee('WHM / cPanel');
    }

    public function test_installing_from_catalog_creates_integration_and_event_subscriptions(): void
    {
        Livewire::actingAs($this->admin())
            ->test(IntegrationCatalog::class)
            ->call('install', 'monday');

        $integration = Integration::where('key', 'monday')->firstOrFail();
        $this->assertFalse($integration->is_enabled);          // se instala desactivada
        $this->assertSame('not_configured', $integration->status);

        // Suscribe (desactivados) los eventos del manifiesto de Monday.
        $this->assertDatabaseHas('integration_events', [
            'integration_id' => $integration->id,
            'event_name' => 'TicketCreated',
            'is_enabled' => false,
        ]);
    }

    public function test_installing_twice_does_not_duplicate(): void
    {
        $this->integration();

        Livewire::actingAs($this->admin())
            ->test(IntegrationCatalog::class)
            ->call('install', 'monday');

        $this->assertSame(1, Integration::where('key', 'monday')->count());
    }

    public function test_toggle_enables_and_disables_integration(): void
    {
        $integration = $this->integration(enabled: false);

        Livewire::actingAs($this->admin())
            ->test(IntegrationIndex::class)
            ->call('toggle', $integration->id);

        $this->assertTrue($integration->fresh()->is_enabled);

        Livewire::actingAs($this->admin())
            ->test(IntegrationIndex::class)
            ->call('toggle', $integration->id);

        $this->assertFalse($integration->fresh()->is_enabled);
    }

    public function test_test_connection_uses_fake_provider_and_sets_connected(): void
    {
        $integration = $this->integration(enabled: true);

        Livewire::actingAs($this->admin())
            ->test(IntegrationIndex::class)
            ->call('testConnection', $integration->id);

        $integration->refresh();
        $this->assertSame('connected', $integration->status);
        $this->assertNotNull($integration->last_tested_at);
        $this->assertNull($integration->last_error);
    }

    public function test_admin_can_uninstall_an_integration(): void
    {
        $integration = $this->integration();
        \App\Domain\Integrations\Models\IntegrationSecret::putFor($integration, 'api_token', 'x');

        Livewire::actingAs($this->admin())
            ->test(IntegrationIndex::class)
            ->call('uninstall', $integration->id);

        $this->assertDatabaseMissing('integrations', ['id' => $integration->id]);
        // La cascada borra también sus secretos.
        $this->assertDatabaseMissing('integration_secrets', ['integration_id' => $integration->id]);
    }

    public function test_admin_can_view_logs(): void
    {
        $integration = $this->integration();
        IntegrationLog::create([
            'integration_id' => $integration->id,
            'event' => 'event.TicketCreated',
            'direction' => 'outbound',
            'success' => true,
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.integrations.logs'))
            ->assertOk()
            ->assertSee('Registros de integración')
            ->assertSee('event.TicketCreated');
    }

    public function test_support_role_cannot_access_integrations(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $this->actingAs($support)
            ->get(route('admin.integrations.index'))
            ->assertForbidden();
    }
}
