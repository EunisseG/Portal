<?php

namespace Tests\Feature\Integrations;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationEventSubscription;
use App\Domain\Integrations\Models\IntegrationReference;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\TicketService;
use App\Integrations\DTO\TicketPayload;
use App\Integrations\Jobs\DispatchIntegrationEvent;
use App\Integrations\Providers\Monday\FakeMondayTicketProvider;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Registry\IntegrationRegistry;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MondayTicketSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake(TicketService::DISK);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    private function mondayIntegration(bool $enabled = true, bool $subscribed = true): Integration
    {
        $integration = Integration::create([
            'key' => 'monday',
            'name' => 'Monday.com',
            'capability' => IntegrationCapability::Tickets->value,
            'is_enabled' => $enabled,
            'status' => 'connected',
        ]);

        IntegrationEventSubscription::create([
            'integration_id' => $integration->id,
            'event_name' => 'TicketCreated',
            'is_enabled' => $subscribed,
        ]);

        return $integration;
    }

    public function test_creating_ticket_dispatches_integration_job_when_monday_subscribed(): void
    {
        Queue::fake();
        $this->mondayIntegration();
        $user = $this->clientUser();

        app(TicketService::class)->create(
            $user->client->id,
            $user,
            ['subject' => 'Falla', 'department' => 'support', 'priority' => 'normal'],
            'Descripción del problema',
        );

        Queue::assertPushed(DispatchIntegrationEvent::class, fn ($job) => $job->eventName === 'TicketCreated');
    }

    public function test_no_integration_job_when_monday_not_subscribed(): void
    {
        Queue::fake();
        $this->mondayIntegration(subscribed: false);
        $user = $this->clientUser();

        app(TicketService::class)->create(
            $user->client->id,
            $user,
            ['subject' => 'Falla', 'department' => 'support', 'priority' => 'normal'],
            'Descripción',
        );

        Queue::assertNotPushed(DispatchIntegrationEvent::class);
    }

    public function test_fake_monday_provider_creates_external_reference(): void
    {
        $integration = $this->mondayIntegration();
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create([
            'client_id' => $client->id,
            'number' => 'TK-2026-00042',
        ]);

        $provider = new FakeMondayTicketProvider($integration);
        $result = $provider->handleEvent('TicketCreated', [
            'ticket_id' => $ticket->id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->assertTrue($result->success);

        // WORTEV guarda la referencia externa: Ticket ↔ Monday item.
        $reference = IntegrationReference::where('integration_id', $integration->id)
            ->where('referenceable_type', (new Ticket())->getMorphClass())
            ->where('referenceable_id', $ticket->id)
            ->firstOrFail();

        $this->assertSame('MON-'.$ticket->id, $reference->external_id);
        $this->assertSame('synced', $reference->sync_status);
        $this->assertNotNull($reference->last_synced_at);
        $this->assertStringContainsString('monday.com', $reference->external_url);
    }

    public function test_end_to_end_job_syncs_ticket_to_monday(): void
    {
        $integration = $this->mondayIntegration();
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);

        // Ejecuta el job directamente (como haría la cola).
        (new DispatchIntegrationEvent($integration->id, 'TicketCreated', [
            'ticket_id' => $ticket->id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'status' => 'open',
            'priority' => 'normal',
        ]))->handle(
            app(IntegrationRegistry::class),
            app(\App\Integrations\Actions\RecordIntegrationLog::class),
        );

        $this->assertDatabaseHas('integration_references', [
            'integration_id' => $integration->id,
            'referenceable_id' => $ticket->id,
            'external_id' => 'MON-'.$ticket->id,
            'sync_status' => 'synced',
        ]);

        // Se registró el log de la operación.
        $this->assertDatabaseHas('integration_logs', [
            'integration_id' => $integration->id,
            'event' => 'event.TicketCreated',
            'success' => true,
        ]);
    }

    public function test_ticket_payload_round_trips(): void
    {
        $payload = new TicketPayload(1, 'TK-1', 'Asunto', 'open', 'high', 'Ana');
        $restored = TicketPayload::fromArray($payload->toArray());

        $this->assertSame('TK-1', $restored->number);
        $this->assertSame('Ana', $restored->clientName);
    }
}
