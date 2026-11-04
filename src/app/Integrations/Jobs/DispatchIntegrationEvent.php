<?php

namespace App\Integrations\Jobs;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Integrations\Contracts\IntegrationDispatchable;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Entrega un evento de dominio a UNA integración suscrita. Se encola (cola
 * "integrations") para no bloquear la operación de negocio.
 *
 * Resuelve el provider desde el registro y, si el provider sabe manejar el
 * evento (implementa IntegrationDispatchable), delega la ejecución. Todo queda
 * registrado en integration_logs.
 */
class DispatchIntegrationEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 5;

    public function __construct(
        public readonly int $integrationId,
        public readonly string $eventName,
        /** @var array<string, mixed> */
        public readonly array $payload,
    ) {
        $this->onQueue('integrations');
    }

    /** Backoff exponencial entre reintentos (segundos). */
    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    public function handle(IntegrationRegistry $registry, RecordIntegrationLog $log): void
    {
        $integration = Integration::find($this->integrationId);

        // Integración eliminada o desactivada: no hacer nada (se registra skip).
        if (! $integration || ! $integration->isActive()) {
            $log->record($this->logEvent(), [
                'integration_id' => $this->integrationId,
                'success' => true,
                'message' => 'Integración inactiva o inexistente: omitido.',
            ]);

            return;
        }

        $provider = $registry->resolve($integration);

        // Sin provider concreto todavía (fase de diseño): se registra el intento
        // sin fallar. Los providers reales/fake se enlazan en fases posteriores.
        if (! $provider instanceof IntegrationDispatchable) {
            $log->record($this->logEvent(), [
                'integration_id' => $integration->id,
                'success' => true,
                'message' => 'Sin manejador de evento para esta integración (pendiente).',
            ]);

            return;
        }

        $start = microtime(true);

        try {
            $result = $provider->handleEvent($this->eventName, $this->payload);

            $log->record($this->logEvent(), [
                'integration_id' => $integration->id,
                'success' => $result->success,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'message' => $result->success ? null : $result->message,
                'external_reference' => $result->data['external_reference'] ?? null,
            ]);

            if (! $result->success) {
                // Marca error en la integración; permite reintento del job.
                $integration->update(['status' => 'error', 'last_error' => $result->message]);
                $this->fail(new \RuntimeException($result->message));
            }
        } catch (\Throwable $e) {
            $log->record($this->logEvent(), [
                'integration_id' => $integration->id,
                'success' => false,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'message' => $e->getMessage(),
            ]);

            throw $e; // deja que la cola reintente con backoff
        }
    }

    private function logEvent(): string
    {
        return 'event.'.$this->eventName;
    }
}
