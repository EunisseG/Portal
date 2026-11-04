<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Integrations\Providers\Monday\FakeMondayTicketProvider;
use App\Integrations\Providers\Monday\MondayTicketProvider;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Registry\IntegrationRegistry;
use App\Integrations\DTO\TicketPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MondayProviderTest extends TestCase
{
    use RefreshDatabase;

    private function mondayIntegration(bool $configured = true): Integration
    {
        $integration = Integration::create([
            'key' => 'monday',
            'name' => 'Monday.com',
            'capability' => IntegrationCapability::Tickets->value,
            'is_enabled' => true,
            'config' => $configured ? ['board_id' => '999'] : [],
        ]);

        if ($configured) {
            IntegrationSecret::putFor($integration, 'api_token', 'monday-token-abc');
        }

        return $integration;
    }

    public function test_real_provider_creates_item_via_graphql(): void
    {
        Http::fake([
            'api.monday.com/*' => Http::response(['data' => ['create_item' => ['id' => '458721']]], 200),
        ]);

        $provider = new MondayTicketProvider($this->mondayIntegration());
        $result = $provider->createItem(new TicketPayload(1, 'TK-1', 'Falla de correo', 'open', 'high', 'Ana'));

        $this->assertTrue($result->success);
        $this->assertSame('458721', $result->data['external_id']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.monday.com/v2')
                && $request->hasHeader('Authorization', 'Bearer monday-token-abc')
                && str_contains($request['query'], 'create_item');
        });
    }

    public function test_real_provider_reports_graphql_errors_as_failure(): void
    {
        // Monday devuelve 200 con "errors" ante fallos GraphQL.
        Http::fake([
            'api.monday.com/*' => Http::response(['errors' => [['message' => 'Invalid board']]], 200),
        ]);

        $provider = new MondayTicketProvider($this->mondayIntegration());
        $result = $provider->createItem(new TicketPayload(1, 'TK-1', 'x', 'open', 'low'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid board', $result->message);
    }

    public function test_not_configured_returns_failure_without_calling_api(): void
    {
        Http::fake();

        $provider = new MondayTicketProvider($this->mondayIntegration(configured: false));
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }

    public function test_registry_resolves_real_when_configured_and_fake_otherwise(): void
    {
        $configured = $this->mondayIntegration();
        $this->assertInstanceOf(MondayTicketProvider::class, app(IntegrationRegistry::class)->resolve($configured));

        $configured->delete();

        $notConfigured = $this->mondayIntegration(configured: false);
        $this->assertInstanceOf(FakeMondayTicketProvider::class, app(IntegrationRegistry::class)->resolve($notConfigured));
    }

    public function test_handle_event_creates_reference_via_real_provider(): void
    {
        Http::fake([
            'api.monday.com/*' => Http::response(['data' => ['create_item' => ['id' => '777']]], 200),
        ]);

        $integration = $this->mondayIntegration();
        $client = \App\Domain\Clients\Models\Client::factory()->create();
        $ticket = \App\Domain\Tickets\Models\Ticket::factory()->create(['client_id' => $client->id]);

        $provider = new MondayTicketProvider($integration);
        $result = $provider->handleEvent('TicketCreated', [
            'ticket_id' => $ticket->id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('integration_references', [
            'integration_id' => $integration->id,
            'referenceable_id' => $ticket->id,
            'external_id' => '777',
            'sync_status' => 'synced',
        ]);
    }
}
