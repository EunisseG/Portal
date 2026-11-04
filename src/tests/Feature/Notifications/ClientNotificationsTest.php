<?php

namespace Tests\Feature\Notifications;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\MaintenanceStatus;
use App\Domain\Content\Jobs\SendMaintenanceWhatsApp;
use App\Domain\Content\Services\NewsService;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\TicketService;
use App\Livewire\Admin\Content\MaintenanceIndex;
use App\Livewire\Client\NotificationBell;
use App\Domain\Content\Models\Maintenance;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Domain\Services\Models\Server;
use App\Domain\Services\Models\Service;
use App\Integrations\Registry\IntegrationCapability;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ClientNotificationsTest extends TestCase
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

    private function clientUser(?string $whatsapp = null): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id, 'whatsapp' => $whatsapp]);

        return $user->refresh();
    }

    /** Cliente con un servicio de HOSTING alojado en el servidor indicado. */
    private function clientOnServer(Server $server, ?string $whatsapp = null): User
    {
        $user = $this->clientUser($whatsapp);
        Service::factory()->create([
            'client_id' => $user->client->id,
            'server_id' => $server->id,
            'type' => \App\Domain\Services\Enums\ServiceType::Hosting->value,
        ]);

        return $user->refresh();
    }

    /** Cliente con un servicio del tipo indicado (sin servidor). */
    private function clientWithServiceType(string $type, ?string $whatsapp = null): User
    {
        $user = $this->clientUser($whatsapp);
        Service::factory()->create(['client_id' => $user->client->id, 'type' => $type]);

        return $user->refresh();
    }

    public function test_publishing_news_notifies_all_clients(): void
    {
        $c1 = $this->clientUser();
        $c2 = $this->clientUser();

        app(NewsService::class)->create([
            'title' => 'Nueva versión del portal',
            'body' => 'Contenido',
            'status' => ContentStatus::Published->value,
            'published_at' => null,
        ], $this->admin()->id);

        $this->assertSame(1, $c1->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $c2->fresh()->unreadNotifications()->count());
        $this->assertSame('news', $c1->fresh()->notifications()->first()->data['type']);
    }

    public function test_draft_news_does_not_notify(): void
    {
        $client = $this->clientUser();

        app(NewsService::class)->create([
            'title' => 'Borrador',
            'body' => 'Contenido',
            'status' => ContentStatus::Draft->value,
            'published_at' => null,
        ], $this->admin()->id);

        $this->assertSame(0, $client->fresh()->unreadNotifications()->count());
    }

    public function test_creating_maintenance_notifies_clients_and_dispatches_whatsapp(): void
    {
        Queue::fake();
        $client = $this->clientUser();

        Livewire::actingAs($this->admin())
            ->test(MaintenanceIndex::class)
            ->set('title', 'Ventana de mantenimiento')
            ->set('starts_at', '2026-07-01T02:00')
            ->set('status', MaintenanceStatus::Scheduled->value)
            ->set('is_published', true)
            ->call('save');

        $this->assertSame(1, $client->fresh()->unreadNotifications()->count());
        $this->assertSame('maintenance', $client->fresh()->notifications()->first()->data['type']);
        Queue::assertPushed(SendMaintenanceWhatsApp::class);
    }

    public function test_staff_reply_notifies_ticket_owner(): void
    {
        $owner = $this->clientUser();
        $ticket = Ticket::factory()->create(['client_id' => $owner->client->id]);

        app(TicketService::class)->reply($ticket, $this->admin(), 'Respuesta del equipo', true);

        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame('ticket', $owner->fresh()->notifications()->first()->data['type']);
    }

    public function test_client_reply_does_not_notify(): void
    {
        $owner = $this->clientUser();
        $ticket = Ticket::factory()->create(['client_id' => $owner->client->id]);

        app(TicketService::class)->reply($ticket, $owner, 'Comentario del cliente', false);

        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_bell_shows_unread_count_and_marks_all_as_read(): void
    {
        $owner = $this->clientUser();
        $ticket = Ticket::factory()->create(['client_id' => $owner->client->id]);
        app(TicketService::class)->reply($ticket, $this->admin(), 'Respuesta', true);

        Livewire::actingAs($owner->fresh())
            ->test(NotificationBell::class)
            ->assertSee('Respuesta a tu ticket')
            ->call('markAllAsRead');

        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_bell_open_marks_single_notification_as_read(): void
    {
        $owner = $this->clientUser();
        $ticket = Ticket::factory()->create(['client_id' => $owner->client->id]);
        app(TicketService::class)->reply($ticket, $this->admin(), 'Respuesta', true);

        $notificationId = $owner->fresh()->notifications()->first()->id;

        Livewire::actingAs($owner->fresh())
            ->test(NotificationBell::class)
            ->call('open', $notificationId);

        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_server_targeted_maintenance_notifies_only_clients_on_that_server(): void
    {
        Queue::fake();
        $serverA = Server::factory()->create();
        $serverB = Server::factory()->create();

        $onA = $this->clientOnServer($serverA);
        $onB = $this->clientOnServer($serverB);
        $noServer = $this->clientUser();

        Livewire::actingAs($this->admin())
            ->test(MaintenanceIndex::class)
            ->set('service_type', \App\Domain\Services\Enums\ServiceType::Hosting->value)
            ->set('server_id', $serverA->id)
            ->set('title', 'Mantenimiento del servidor A')
            ->set('starts_at', '2026-07-01T02:00')
            ->set('status', MaintenanceStatus::Scheduled->value)
            ->set('is_published', true)
            ->call('save');

        $this->assertSame(1, $onA->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $onB->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $noServer->fresh()->unreadNotifications()->count());
    }

    public function test_service_type_targeted_maintenance_notifies_only_clients_with_that_type(): void
    {
        Queue::fake();
        $emailClient = $this->clientWithServiceType(\App\Domain\Services\Enums\ServiceType::Email->value);
        $licenseClient = $this->clientWithServiceType(\App\Domain\Services\Enums\ServiceType::License->value);

        Livewire::actingAs($this->admin())
            ->test(MaintenanceIndex::class)
            ->set('service_type', \App\Domain\Services\Enums\ServiceType::Email->value)
            ->set('title', 'Mantenimiento del servicio de correo')
            ->set('starts_at', '2026-07-01T02:00')
            ->set('status', MaintenanceStatus::Scheduled->value)
            ->set('is_published', true)
            ->call('save');

        $this->assertSame(1, $emailClient->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $licenseClient->fresh()->unreadNotifications()->count());
    }

    public function test_global_maintenance_notifies_all_clients(): void
    {
        Queue::fake();
        $server = Server::factory()->create();
        $onServer = $this->clientOnServer($server);
        $plain = $this->clientUser();

        Livewire::actingAs($this->admin())
            ->test(MaintenanceIndex::class)
            ->set('server_id', null)
            ->set('title', 'Mantenimiento global')
            ->set('starts_at', '2026-07-01T02:00')
            ->set('status', MaintenanceStatus::Scheduled->value)
            ->set('is_published', true)
            ->call('save');

        $this->assertSame(1, $onServer->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $plain->fresh()->unreadNotifications()->count());
    }

    public function test_client_only_sees_maintenances_for_their_servers_and_global(): void
    {
        $serverA = Server::factory()->create();
        $serverB = Server::factory()->create();
        $client = $this->clientOnServer($serverA);

        Maintenance::create([
            'service_type' => \App\Domain\Services\Enums\ServiceType::Hosting->value,
            'server_id' => $serverA->id,
            'title' => 'MANTENIMIENTO SERVIDOR A',
            'starts_at' => now()->addDay(),
            'status' => MaintenanceStatus::Scheduled->value,
            'is_published' => true,
        ]);
        Maintenance::create([
            'service_type' => \App\Domain\Services\Enums\ServiceType::Hosting->value,
            'server_id' => $serverB->id,
            'title' => 'MANTENIMIENTO SERVIDOR B',
            'starts_at' => now()->addDay(),
            'status' => MaintenanceStatus::Scheduled->value,
            'is_published' => true,
        ]);
        Maintenance::create([
            'service_type' => null,
            'server_id' => null,
            'title' => 'MANTENIMIENTO GLOBAL',
            'starts_at' => now()->addDay(),
            'status' => MaintenanceStatus::Scheduled->value,
            'is_published' => true,
        ]);

        $this->actingAs($client)
            ->get(route('client.maintenances.index'))
            ->assertOk()
            ->assertSee('MANTENIMIENTO SERVIDOR A')
            ->assertSee('MANTENIMIENTO GLOBAL')
            ->assertDontSee('MANTENIMIENTO SERVIDOR B');
    }

    public function test_server_targeted_whatsapp_only_reaches_clients_on_that_server(): void
    {
        $integration = Integration::create([
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'capability' => IntegrationCapability::Notifications->value,
            'is_enabled' => true,
            'config' => ['phone_number_id' => '123456', 'template_maintenance' => 'maintenance_notice'],
        ]);
        IntegrationSecret::putFor($integration, 'access_token', 'meta-token-xyz');

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $serverA = Server::factory()->create();
        $serverB = Server::factory()->create();
        $this->clientOnServer($serverA, '5511111111'); // en A, con número → recibe
        $this->clientOnServer($serverB, '5522222222'); // en B → NO recibe

        $maintenance = Maintenance::create([
            'service_type' => \App\Domain\Services\Enums\ServiceType::Hosting->value,
            'server_id' => $serverA->id,
            'title' => 'Servidor A',
            'starts_at' => now()->addDay(),
            'status' => MaintenanceStatus::Scheduled->value,
            'is_published' => true,
        ]);

        (new SendMaintenanceWhatsApp($maintenance->id))->handle(app(\App\Integrations\Contracts\WhatsAppGateway::class));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->data()['to'] === '5511111111');
    }

    public function test_maintenance_whatsapp_job_sends_to_clients_with_number(): void
    {
        // Integración WhatsApp real configurada + plantilla de mantenimiento.
        $integration = Integration::create([
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'capability' => IntegrationCapability::Notifications->value,
            'is_enabled' => true,
            'config' => ['phone_number_id' => '123456', 'template_maintenance' => 'maintenance_notice'],
        ]);
        IntegrationSecret::putFor($integration, 'access_token', 'meta-token-xyz');

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $this->clientUser('5512345678');   // con número → recibe
        $this->clientUser(null);            // sin número → se omite

        $maintenance = Maintenance::create([
            'title' => 'Ventana nocturna',
            'starts_at' => now()->addDay(),
            'status' => MaintenanceStatus::Scheduled->value,
            'is_published' => true,
        ]);

        (new SendMaintenanceWhatsApp($maintenance->id))->handle(app(\App\Integrations\Contracts\WhatsAppGateway::class));

        // Solo un envío: el cliente con número.
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), '/123456/messages')
                && $body['template']['name'] === 'maintenance_notice'
                && $body['to'] === '5512345678';
        });
    }
}
