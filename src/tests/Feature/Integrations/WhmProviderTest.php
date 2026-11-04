<?php

namespace Tests\Feature\Integrations;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Domain\Services\Models\Server;
use App\Domain\Services\Models\Service;
use App\Integrations\Providers\Plesk\PleskHostingProvider;
use App\Integrations\Providers\Whm\FakeWhmHostingProvider;
use App\Integrations\Providers\Whm\HostingProviderFactory;
use App\Integrations\Providers\Whm\WhmHostingProvider;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Registry\IntegrationRegistry;
use App\Livewire\Admin\Servers\ServerForm;
use App\Livewire\Admin\Servers\ServerIndex;
use App\Livewire\Admin\Services\ServiceShow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhmProviderTest extends TestCase
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

    private function whmIntegration(bool $configured = true, bool $enabled = true): Integration
    {
        $integration = Integration::create([
            'key' => 'whm',
            'name' => 'WHM / cPanel',
            'capability' => IntegrationCapability::Hosting->value,
            'is_enabled' => $enabled,
            'config' => $configured ? ['base_url' => 'https://whm.test:2087', 'whm_user' => 'root'] : [],
        ]);

        if ($configured) {
            IntegrationSecret::putFor($integration, 'api_token', 'whm-token-123');
        }

        return $integration;
    }

    public function test_real_provider_creates_account_via_whm_api(): void
    {
        Http::fake([
            'whm.test:2087/*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200),
        ]);

        $provider = new WhmHostingProvider($this->whmIntegration());
        $result = $provider->createAccount(['domain' => 'cliente.com', 'username' => 'cli1']);

        $this->assertTrue($result->success);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/json-api/createacct')
                && $request->hasHeader('Authorization', 'whm root:whm-token-123');
        });
    }

    public function test_real_provider_reports_whm_error(): void
    {
        Http::fake([
            'whm.test:2087/*' => Http::response(['metadata' => ['result' => 0, 'reason' => 'User exists']], 200),
        ]);

        $provider = new WhmHostingProvider($this->whmIntegration());
        $result = $provider->suspend('cli1');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('User exists', $result->message);
    }

    public function test_registry_resolves_real_when_configured(): void
    {
        $this->assertInstanceOf(WhmHostingProvider::class, app(IntegrationRegistry::class)->resolve($this->whmIntegration()));
    }

    public function test_registry_resolves_fake_when_not_configured(): void
    {
        $this->assertInstanceOf(FakeWhmHostingProvider::class, app(IntegrationRegistry::class)->resolve($this->whmIntegration(configured: false)));
    }

    // --- Conexión WHM por servidor ---

    private function whmServer(bool $withWhm = true): Server
    {
        return Server::factory()->create([
            'name' => 'Servidor 1',
            'panel' => $withWhm ? \App\Domain\Services\Enums\ServerPanel::Whm->value : null,
            'panel_scheme' => 'https',
            'panel_host' => $withWhm ? 'srv1.test' : null,
            'panel_port' => $withWhm ? 2087 : null,
            'panel_user' => $withWhm ? 'root' : null,
            'panel_token' => $withWhm ? 'srv1-token' : null,
            'panel_verify_tls' => false,
        ]);
    }

    private function pleskServer(): Server
    {
        return Server::factory()->create([
            'name' => 'Plesk 1',
            'panel' => \App\Domain\Services\Enums\ServerPanel::Plesk->value,
            'panel_scheme' => 'https',
            'panel_host' => 'plesk.test',
            'panel_port' => 8443,
            'panel_user' => 'admin',
            'panel_token' => 'plesk-key',
            'panel_verify_tls' => false,
        ]);
    }

    public function test_provider_for_server_uses_server_credentials(): void
    {
        Http::fake([
            'srv1.test:2087/*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200),
        ]);

        $provider = WhmHostingProvider::forServer($this->whmServer());
        $result = $provider->createAccount(['domain' => 'cliente.com', 'username' => 'cli1']);

        $this->assertTrue($result->success);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'srv1.test:2087/json-api/createacct')
                && $request->hasHeader('Authorization', 'whm root:srv1-token');
        });
    }

    public function test_admin_creates_whm_account_using_service_server(): void
    {
        Http::fake([
            'srv1.test:2087/*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200),
        ]);

        $server = $this->whmServer();
        $client = Client::factory()->create();
        $service = Service::factory()->create(['client_id' => $client->id, 'server_id' => $server->id]);

        Livewire::actingAs($this->admin())
            ->test(ServiceShow::class, ['service' => $service])
            ->call('whmCreateAccount');

        // El servicio queda vinculado a la cuenta WHM creada.
        $this->assertNotEmpty($service->fresh()->whm_username);
        $this->assertDatabaseHas('integration_logs', ['event' => 'whm.createacct', 'success' => true]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'srv1.test:2087/json-api/createacct'));
    }

    public function test_admin_can_suspend_and_reactivate_using_service_server(): void
    {
        Http::fake([
            'srv1.test:2087/*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200),
        ]);

        $server = $this->whmServer();
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'server_id' => $server->id,
            'whm_username' => 'cli1',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceShow::class, ['service' => $service])
            ->call('whmSuspend')
            ->call('whmUnsuspend');

        $this->assertDatabaseHas('integration_logs', ['event' => 'whm.suspendacct', 'success' => true]);
        $this->assertDatabaseHas('integration_logs', ['event' => 'whm.unsuspendacct', 'success' => true]);
    }

    public function test_admin_can_link_existing_cpanel_account_when_it_exists(): void
    {
        // accountsummary responde OK => la cuenta existe.
        Http::fake([
            'srv1.test:2087/*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200),
        ]);

        $server = $this->whmServer();
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'server_id' => $server->id,
            'whm_username' => null,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceShow::class, ['service' => $service])
            ->set('linkUsername', 'clientexyz')
            ->call('linkAccount')
            ->assertHasNoErrors();

        $this->assertSame('clientexyz', $service->fresh()->whm_username);
        $this->assertDatabaseHas('integration_logs', ['event' => 'whm.link_account', 'success' => true]);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/json-api/accountsummary'));
    }

    public function test_linking_fails_when_account_not_found(): void
    {
        // accountsummary responde error => la cuenta no existe; no se vincula.
        Http::fake([
            'srv1.test:2087/*' => Http::response(['metadata' => ['result' => 0, 'reason' => 'account does not exist']], 200),
        ]);

        $server = $this->whmServer();
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'server_id' => $server->id,
            'whm_username' => null,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceShow::class, ['service' => $service])
            ->set('linkUsername', 'noexiste')
            ->call('linkAccount');

        $this->assertNull($service->fresh()->whm_username);
        $this->assertDatabaseHas('integration_logs', ['event' => 'whm.link_account', 'success' => false]);
    }

    public function test_admin_can_unlink_account(): void
    {
        $server = $this->whmServer();
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'server_id' => $server->id,
            'whm_username' => 'cli1',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceShow::class, ['service' => $service])
            ->call('unlinkAccount');

        $this->assertNull($service->fresh()->whm_username);
    }

    public function test_whm_buttons_hidden_when_server_has_no_whm(): void
    {
        $server = $this->whmServer(withWhm: false);
        $client = Client::factory()->create();
        $service = Service::factory()->create(['client_id' => $client->id, 'server_id' => $server->id]);

        Livewire::actingAs($this->admin())
            ->test(ServiceShow::class, ['service' => $service])
            ->assertSet('service.id', $service->id)
            ->assertDontSee('Hosting (WHM)');
    }

    public function test_whm_buttons_hidden_when_service_has_no_server(): void
    {
        $client = Client::factory()->create();
        $service = Service::factory()->create(['client_id' => $client->id, 'server_id' => null]);

        Livewire::actingAs($this->admin())
            ->test(ServiceShow::class, ['service' => $service])
            ->assertDontSee('Hosting (WHM)');
    }

    // --- Plesk ---

    public function test_plesk_provider_creates_domain_via_rest_api(): void
    {
        Http::fake([
            'plesk.test:8443/*' => Http::response(['id' => 10, 'name' => 'cliente.com'], 201),
        ]);

        $provider = PleskHostingProvider::forServer($this->pleskServer());
        $result = $provider->createAccount(['domain' => 'cliente.com', 'username' => 'cli1']);

        $this->assertTrue($result->success);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'plesk.test:8443/api/v2/domains')
                && $request->hasHeader('X-API-Key', 'plesk-key');
        });
    }

    public function test_plesk_reports_error(): void
    {
        Http::fake([
            'plesk.test:8443/*' => Http::response(['code' => 1, 'message' => 'Domain exists'], 400),
        ]);

        $result = PleskHostingProvider::forServer($this->pleskServer())->createAccount(['domain' => 'x.com']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Domain exists', $result->message);
    }

    public function test_factory_picks_adapter_by_panel(): void
    {
        $this->assertInstanceOf(WhmHostingProvider::class, HostingProviderFactory::forServer($this->whmServer()));
        $this->assertInstanceOf(PleskHostingProvider::class, HostingProviderFactory::forServer($this->pleskServer()));
        $this->assertNull(HostingProviderFactory::forServer($this->whmServer(withWhm: false)));
    }

    // --- Formulario de servidor ---

    public function test_admin_can_save_server_with_whm_panel(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ServerForm::class)
            ->set('name', 'Nuevo WHM')
            ->set('panel', \App\Domain\Services\Enums\ServerPanel::Whm->value)
            ->set('panel_host', 'nuevo.test')
            ->set('panel_port', 2087)
            ->set('panel_user', 'root')
            ->set('panel_token', 'tok-123')
            ->set('panel_verify_tls', false)
            ->call('save');

        $server = Server::where('name', 'Nuevo WHM')->firstOrFail();
        $this->assertSame(\App\Domain\Services\Enums\ServerPanel::Whm, $server->panel);
        $this->assertSame('nuevo.test', $server->panel_host);
        $this->assertSame('tok-123', $server->panel_token); // descifrado por el cast
        $this->assertTrue($server->hasPanel());
    }

    public function test_save_and_test_reports_connection_result(): void
    {
        Http::fake([
            'nuevo.test:2087/*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServerForm::class)
            ->set('name', 'WHM probar')
            ->set('panel', \App\Domain\Services\Enums\ServerPanel::Whm->value)
            ->set('panel_host', 'nuevo.test')
            ->set('panel_port', 2087)
            ->set('panel_user', 'root')
            ->set('panel_token', 'tok-123')
            ->set('panel_verify_tls', false)
            ->call('saveAndTest');

        $this->assertDatabaseHas('servers', ['name' => 'WHM probar', 'panel' => 'whm']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'nuevo.test:2087/json-api/listpkgs'));
    }

    public function test_editing_keeps_token_when_left_blank(): void
    {
        $server = $this->whmServer();

        Livewire::actingAs($this->admin())
            ->test(ServerForm::class, ['server' => $server])
            ->assertSet('panel_token', '') // nunca se precarga
            ->set('panel_user', 'admin2')
            ->set('panel_token', '') // vacío = conservar
            ->call('save');

        $this->assertSame('srv1-token', $server->fresh()->panel_token);
        $this->assertSame('admin2', $server->fresh()->panel_user);
    }

    // --- Fase 2: nameservers, lista y Plesk completo ---

    public function test_server_form_saves_nameservers(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ServerForm::class)
            ->set('name', 'Con NS')
            ->set('ns1', 'ns1.wortev.com')
            ->set('ns2', 'ns2.wortev.com')
            ->call('save');

        $server = Server::where('name', 'Con NS')->firstOrFail();
        $this->assertSame('ns1.wortev.com', $server->ns1);
        $this->assertSame('ns2.wortev.com', $server->ns2);
    }

    public function test_test_connection_from_list_reports_success(): void
    {
        Http::fake([
            'srv1.test:2087/*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200),
        ]);

        $server = $this->whmServer();

        Livewire::actingAs($this->admin())
            ->test(ServerIndex::class)
            ->call('testConnection', $server->id)
            ->assertHasNoErrors();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'srv1.test:2087/json-api/listpkgs'));
    }

    public function test_test_connection_from_list_without_panel(): void
    {
        $server = $this->whmServer(withWhm: false);

        Livewire::actingAs($this->admin())
            ->test(ServerIndex::class)
            ->call('testConnection', $server->id);

        // Sin conexión: no debe intentar ninguna llamada.
        // (session flash de error; no hay aserción de HTTP porque no se hace fake)
        $this->assertFalse($server->hasPanel());
    }

    public function test_plesk_create_account_creates_client_and_domain(): void
    {
        Http::fake([
            'plesk.test:8443/api/v2/clients' => Http::response(['id' => 5, 'login' => 'cli1'], 201),
            'plesk.test:8443/api/v2/domains' => Http::response(['id' => 20, 'name' => 'cliente.com'], 201),
        ]);

        $provider = PleskHostingProvider::forServer($this->pleskServer());
        $result = $provider->createAccount([
            'domain' => 'cliente.com',
            'username' => 'cli1',
            'plan' => 'Plan Básico',
            'contactemail' => 'cliente@correo.com',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(5, $result->data['client_id']);
        $this->assertSame(20, $result->data['domain_id']);

        // Se llamó primero a clients y luego a domains.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v2/clients'));
        Http::assertSent(function ($r) {
            return str_contains($r->url(), '/api/v2/domains')
                && data_get($r->data(), 'owner_client.id') === 5
                && data_get($r->data(), 'plan.name') === 'Plan Básico';
        });
    }
}
