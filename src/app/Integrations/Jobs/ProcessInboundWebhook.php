<?php

namespace App\Integrations\Jobs;

use App\Domain\Integrations\Models\WebhookInboxEntry;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Procesa un webhook entrante ya recibido y validado en el buzón. Se ejecuta
 * en cola para no bloquear la respuesta al proveedor. Marca la entrada como
 * procesada (idempotencia) y registra el resultado.
 */
class ProcessInboundWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $inboxId,
        public readonly string $provider,
        public readonly array $payload,
    ) {
        $this->onQueue('integrations');
    }

    public function handle(IntegrationRegistry $registry, RecordIntegrationLog $log): void
    {
        $entry = WebhookInboxEntry::find($this->inboxId);

        // Idempotencia: si ya fue procesado, no repetir.
        if (! $entry || $entry->processed_at !== null) {
            return;
        }

        $handler = $registry->webhookHandler($this->provider);

        if (! $handler) {
            $log->record('webhook.received', [
                'direction' => 'inbound',
                'success' => false,
                'message' => "Sin handler para el proveedor {$this->provider}.",
            ]);

            return;
        }

        $handler->process($this->payload);

        $entry->update(['processed_at' => now()]);

        $log->record('webhook.processed', [
            'direction' => 'inbound',
            'success' => true,
            'external_reference' => $this->provider.':'.$entry->external_event_id,
        ]);
    }
}
