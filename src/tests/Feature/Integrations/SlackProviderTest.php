<?php

namespace Tests\Feature\Integrations;

use App\Domain\Clients\Models\Client;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Domain\Tickets\Jobs\SendTicketSlackNotification;
use App\Integrations\Contracts\NotificationGateway;
use App\Integrations\Providers\Slack\FakeSlackProvider;
use App\Integrations\Providers\Slack\SlackProvider;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackProviderTest extends TestCase
{
    use RefreshDatabase;

    private string $webhook = 'https://hooks.slack.com/services/T000/B000/xyz';

    private function slackIntegration(bool $configured = true, bool $enabled = true): Integration
    {
        $integration = Integration::create([
            'key' => 'slack',
            'name' => 'Slack',
            'capability' => IntegrationCapability::Notifications->value,
            'is_enabled' => $enabled,
            'config' => ['channel' => '#soporte'],
        ]);

        if ($configured) {
            IntegrationSecret::putFor($integration, 'webhook_url', $this->webhook);
        }

        return $integration;
    }

    public function test_real_provider_posts_to_incoming_webhook(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        $provider = new SlackProvider($this->slackIntegration());
        $result = $provider->notify('Hola equipo');

        $this->assertTrue($result->success);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com')
                && $request['text'] === 'Hola equipo'
                && $request['channel'] === '#soporte';
        });
    }

    public function test_real_provider_reports_http_error(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('invalid_payload', 400),
        ]);

        $result = (new SlackProvider($this->slackIntegration()))->notify('x');

        $this->assertFalse($result->success);
    }

    public function test_real_provider_does_not_send_when_not_configured(): void
    {
        Http::fake();

        $result = (new SlackProvider($this->slackIntegration(configured: false)))->notify('x');

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }

    public function test_registry_resolves_real_when_configured_and_fake_otherwise(): void
    {
        $registry = app(IntegrationRegistry::class);

        $configured = $this->slackIntegration();
        $this->assertInstanceOf(SlackProvider::class, $registry->resolve($configured));

        $configured->delete();

        $this->assertInstanceOf(FakeSlackProvider::class, $registry->resolve($this->slackIntegration(configured: false)));
    }

    public function test_notification_gateway_binding_resolves_fake_without_integration(): void
    {
        $this->assertInstanceOf(FakeSlackProvider::class, app(NotificationGateway::class));
    }

    public function test_client_ticket_dispatches_slack_notification(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $client = Client::factory()->create();
        $author = $client->user;

        app(\App\Domain\Tickets\Services\TicketService::class)->create(
            $client->id,
            $author,
            ['subject' => 'Falla', 'department' => 'support', 'priority' => 'high'],
            'Cuerpo del ticket',
            [],
            false,
        );

        \Illuminate\Support\Facades\Queue::assertPushed(SendTicketSlackNotification::class);
    }

    public function test_staff_ticket_does_not_dispatch_slack_notification(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $client = Client::factory()->create();
        $staff = \App\Models\User::factory()->create();

        app(\App\Domain\Tickets\Services\TicketService::class)->create(
            $client->id,
            $staff,
            ['subject' => 'Falla', 'department' => 'support', 'priority' => 'high'],
            'Cuerpo del ticket',
            [],
            true,
        );

        \Illuminate\Support\Facades\Queue::assertNotPushed(SendTicketSlackNotification::class);
    }
}
