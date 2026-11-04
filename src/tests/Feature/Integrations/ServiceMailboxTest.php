<?php

namespace Tests\Feature\Integrations;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\ServerPanel;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Server;
use App\Domain\Services\Models\Service;
use App\Integrations\Providers\Whm\WhmHostingProvider;
use App\Livewire\Client\Services\ServiceShow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceMailboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();
    }

    private function whmServer(): Server
    {
        return Server::factory()->create([
            'name' => 'Srv Mail',
            'panel' => ServerPanel::Whm->value,
            'panel_scheme' => 'https',
            'panel_host' => 'mail.test',
            'panel_port' => 2087,
            'panel_user' => 'root',
            'panel_token' => 'tok-123',
            'panel_verify_tls' => false,
        ]);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_real_provider_lists_and_normalizes_mailboxes(): void
    {
        Http::fake([
            'mail.test:2087/*' => Http::response([
                'metadata' => ['result' => 1, 'reason' => 'OK'],
                'cpanelresult' => [
                    'data' => [
                        ['email' => 'a@demo.com', '_diskused' => 100, '_diskquota' => 1000],
                        ['email' => 'b@demo.com', '_diskused' => 50, '_diskquota' => 'unlimited'],
                    ],
                ],
            ], 200),
        ]);

        $provider = WhmHostingProvider::forServer($this->whmServer());
        $result = $provider->listEmailAccounts('demouser');

        $this->assertTrue($result->success);
        $boxes = $result->data['mailboxes'];
        $this->assertCount(2, $boxes);
        $this->assertSame('a@demo.com', $boxes[0]['email']);
        $this->assertSame(1000.0, $boxes[0]['disk_quota_mb']);
        $this->assertSame(10, $boxes[0]['percent']);
        // Cuota ilimitada => null y sin porcentaje.
        $this->assertNull($boxes[1]['disk_quota_mb']);
        $this->assertNull($boxes[1]['percent']);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/json-api/cpanel')
            && str_contains($r->url(), 'list_pops_with_disk'));
    }

    public function test_client_sees_mailboxes_for_own_hosting_service(): void
    {
        Http::fake([
            'mail.test:2087/*' => Http::response([
                'metadata' => ['result' => 1],
                'cpanelresult' => ['data' => [
                    ['email' => 'hola@cliente.com', '_diskused' => 200, '_diskquota' => 2000],
                ]],
            ], 200),
        ]);

        $me = $this->clientUser();
        $server = $this->whmServer();
        $service = Service::factory()->create([
            'client_id' => $me->client->id,
            'server_id' => $server->id,
            'type' => ServiceType::Hosting->value,
            'whm_username' => 'cliuser',
        ]);

        Livewire::actingAs($me)
            ->test(ServiceShow::class, ['service' => $service])
            ->assertOk()
            ->assertSee('hola@cliente.com')
            ->assertSee('Correos');
    }

    public function test_client_cannot_view_another_clients_service(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();
        $server = $this->whmServer();
        $foreign = Service::factory()->create([
            'client_id' => $other->client->id,
            'server_id' => $server->id,
            'type' => ServiceType::Hosting->value,
            'whm_username' => 'otro',
        ]);

        Livewire::actingAs($me)
            ->test(ServiceShow::class, ['service' => $foreign])
            ->assertNotFound();
    }

    public function test_hosting_service_without_account_shows_notice(): void
    {
        Http::fake();

        $me = $this->clientUser();
        $server = $this->whmServer();
        $service = Service::factory()->create([
            'client_id' => $me->client->id,
            'server_id' => $server->id,
            'type' => ServiceType::Hosting->value,
            'whm_username' => null,
        ]);

        Livewire::actingAs($me)
            ->test(ServiceShow::class, ['service' => $service])
            ->assertOk()
            ->assertSee('aún no tiene una cuenta de hosting');

        Http::assertNothingSent();
    }
}
