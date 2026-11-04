<?php

namespace App\Integrations\Events;

use App\Domain\Integrations\Models\IntegrationEventSubscription;
use App\Integrations\Jobs\DispatchIntegrationEvent;

/**
 * Listener genérico que conecta el bus de eventos de dominio con las
 * integraciones. Para cada evento que implemente IntegrationEvent, busca las
 * integraciones ACTIVAS suscritas a ese evento y despacha un Job por cada una.
 *
 * Añadir un evento o una integración NO requiere tocar los módulos de negocio.
 */
class IntegrationEventSubscriber
{
    public function handle(IntegrationEvent $event): void
    {
        $subscriptions = IntegrationEventSubscription::query()
            ->where('event_name', $event->eventName())
            ->where('is_enabled', true)
            ->with('integration')
            ->get();

        foreach ($subscriptions as $subscription) {
            $integration = $subscription->integration;

            if (! $integration || ! $integration->isActive()) {
                continue;
            }

            DispatchIntegrationEvent::dispatch(
                $integration->id,
                $event->eventName(),
                $event->integrationPayload(),
            );
        }
    }
}
