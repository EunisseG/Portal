<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationEventSubscription;
use App\Integrations\Events\IntegrationEvent;
use App\Integrations\Jobs\DispatchIntegrationEvent;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Evento de prueba que implementa el contrato integrable. */
class StubIntegrationEvent implements IntegrationEvent
{
    public function __construct(private readonly array $payload = []) {}

    public function eventName(): string
    {
        return 'StubEvent';
    }

    public function integrationPayload(): array
    {
        return $this->payload ?: ['id' => 1, 'foo' => 'bar'];
    }
}

class IntegrationEventsTest extends TestCase
{
    use RefreshDatabase;

    private function integration(bool $enabled = true): Integration
    {
        return Integration::create([
            'key' => 'monday',
            'name' => 'Monday.com',
            'capability' => IntegrationCapability::Tickets->value,
            'is_enabled' => $enabled,
            'status' => 'connected',
        ]);
    }

    public function test_event_dispatches_a_job_for_each_subscribed_active_integration(): void
    {
        Queue::fake();

        $integration = $this->integration();
        IntegrationEventSubscription::create([
            'integration_id' => $integration->id,
            'event_name' => 'StubEvent',
            'is_enabled' => true,
        ]);

        event(new StubIntegrationEvent(['id' => 42]));

        Queue::assertPushed(DispatchIntegrationEvent::class, function ($job) use ($integration) {
            return $job->integrationId === $integration->id
                && $job->eventName === 'StubEvent'
                && $job->payload['id'] === 42;
        });
    }

    public function test_job_is_queued_on_the_integrations_queue(): void
    {
        Queue::fake();

        $integration = $this->integration();
        IntegrationEventSubscription::create([
            'integration_id' => $integration->id,
            'event_name' => 'StubEvent',
            'is_enabled' => true,
        ]);

        event(new StubIntegrationEvent());

        Queue::assertPushed(DispatchIntegrationEvent::class, fn ($job) => $job->queue === 'integrations');
    }

    public function test_no_job_when_integration_is_disabled(): void
    {
        Queue::fake();

        $integration = $this->integration(enabled: false);
        IntegrationEventSubscription::create([
            'integration_id' => $integration->id,
            'event_name' => 'StubEvent',
            'is_enabled' => true,
        ]);

        event(new StubIntegrationEvent());

        Queue::assertNothingPushed();
    }

    public function test_no_job_when_subscription_is_disabled(): void
    {
        Queue::fake();

        $integration = $this->integration();
        IntegrationEventSubscription::create([
            'integration_id' => $integration->id,
            'event_name' => 'StubEvent',
            'is_enabled' => false, // suscripción apagada
        ]);

        event(new StubIntegrationEvent());

        Queue::assertNothingPushed();
    }

    public function test_job_with_non_dispatchable_provider_logs_pending_without_failing(): void
    {
        // WhatsApp usa el provider genérico (no implementa IntegrationDispatchable):
        // el job registra el intento como "pendiente" y no lanza excepción.
        $integration = Integration::create([
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'capability' => IntegrationCapability::Notifications->value,
            'is_enabled' => true,
            'status' => 'connected',
        ]);

        (new DispatchIntegrationEvent($integration->id, 'StubEvent', ['id' => 1]))
            ->handle(app(\App\Integrations\Registry\IntegrationRegistry::class), app(\App\Integrations\Actions\RecordIntegrationLog::class));

        $this->assertDatabaseHas('integration_logs', [
            'integration_id' => $integration->id,
            'event' => 'event.StubEvent',
            'success' => true,
        ]);
    }

    public function test_inactive_integration_in_job_is_skipped(): void
    {
        $integration = $this->integration(enabled: false);

        (new DispatchIntegrationEvent($integration->id, 'StubEvent', []))
            ->handle(app(\App\Integrations\Registry\IntegrationRegistry::class), app(\App\Integrations\Actions\RecordIntegrationLog::class));

        $this->assertDatabaseHas('integration_logs', [
            'integration_id' => $integration->id,
            'message' => 'Integración inactiva o inexistente: omitido.',
        ]);
    }
}
