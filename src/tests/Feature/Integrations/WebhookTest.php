<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Domain\Integrations\Models\WebhookInboxEntry;
use App\Integrations\Jobs\ProcessInboundWebhook;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'webhook-secret-xyz';

    private function customApiIntegration(): Integration
    {
        $integration = Integration::create([
            'key' => 'custom_api',
            'name' => 'API personalizada',
            'capability' => IntegrationCapability::Rest->value,
            'is_enabled' => true,
        ]);
        IntegrationSecret::putFor($integration, 'webhook_secret', $this->secret);

        return $integration;
    }

    /** @param array<string,mixed> $payload */
    private function sign(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), $this->secret);
    }

    public function test_valid_webhook_is_accepted_and_queued(): void
    {
        Queue::fake();
        $this->customApiIntegration();

        $payload = ['event_id' => 'evt-1', 'type' => 'ping'];

        $this->postJson('/api/webhooks/custom_api', $payload, [
            'X-Signature' => $this->sign($payload),
        ])->assertStatus(202);

        $this->assertDatabaseHas('webhook_inbox', [
            'provider' => 'custom_api',
            'external_event_id' => 'evt-1',
            'signature_valid' => true,
            'processed_at' => null,
        ]);

        Queue::assertPushed(ProcessInboundWebhook::class, fn ($job) => $job->provider === 'custom_api');
    }

    public function test_invalid_signature_is_rejected(): void
    {
        Queue::fake();
        $this->customApiIntegration();

        $payload = ['event_id' => 'evt-2'];

        $this->postJson('/api/webhooks/custom_api', $payload, [
            'X-Signature' => 'firma-incorrecta',
        ])->assertStatus(401);

        $this->assertDatabaseMissing('webhook_inbox', ['external_event_id' => 'evt-2']);
        Queue::assertNothingPushed();
    }

    public function test_duplicate_event_is_idempotent(): void
    {
        Queue::fake();
        $this->customApiIntegration();

        $payload = ['event_id' => 'evt-3'];
        $headers = ['X-Signature' => $this->sign($payload)];

        $this->postJson('/api/webhooks/custom_api', $payload, $headers)->assertStatus(202);
        // Segundo envío del mismo evento: idempotente (200, no duplica).
        $this->postJson('/api/webhooks/custom_api', $payload, $headers)->assertStatus(200);

        $this->assertSame(1, WebhookInboxEntry::where('external_event_id', 'evt-3')->count());
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->postJson('/api/webhooks/desconocido', ['event_id' => 'x'])
            ->assertStatus(404);
    }

    public function test_job_marks_entry_processed(): void
    {
        $this->customApiIntegration();

        $entry = WebhookInboxEntry::create([
            'provider' => 'custom_api',
            'external_event_id' => 'evt-9',
            'signature_valid' => true,
            'payload_hash' => 'hash',
            'created_at' => now(),
        ]);

        (new ProcessInboundWebhook($entry->id, 'custom_api', ['event_id' => 'evt-9']))
            ->handle(
                app(\App\Integrations\Registry\IntegrationRegistry::class),
                app(\App\Integrations\Actions\RecordIntegrationLog::class),
            );

        $this->assertNotNull($entry->fresh()->processed_at);
        $this->assertDatabaseHas('integration_logs', [
            'event' => 'webhook.processed',
            'direction' => 'inbound',
            'success' => true,
        ]);
    }
}
