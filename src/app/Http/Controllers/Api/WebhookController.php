<?php

namespace App\Http\Controllers\Api;

use App\Domain\Integrations\Models\WebhookInboxEntry;
use App\Http\Controllers\Controller;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Integrations\Jobs\ProcessInboundWebhook;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Punto de entrada de webhooks externos: POST /api/webhooks/{provider}.
 *
 * Flujo: resuelve el handler del proveedor → verifica firma → idempotencia por
 * (provider, external_event_id) → encola el procesamiento → responde 202.
 * No confía en los datos externos: se validan/aplican en el Job.
 */
class WebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        IntegrationRegistry $registry,
        RecordIntegrationLog $log,
    ): JsonResponse {
        $handler = $registry->webhookHandler($provider);

        if (! $handler) {
            return response()->json(['message' => 'Proveedor de webhook desconocido.'], 404);
        }

        // 1) Verificar firma. Nunca se registra la firma ni el secreto.
        if (! $handler->verifySignature($request)) {
            $log->record('webhook.signature_invalid', [
                'direction' => 'inbound',
                'success' => false,
                'http_status' => 401,
                'message' => "Firma inválida ({$provider}).",
            ]);

            return response()->json(['message' => 'Firma inválida.'], 401);
        }

        $externalEventId = $handler->externalEventId($request);
        $payloadHash = hash('sha256', $request->getContent() ?: '');

        // 2) Idempotencia: si ya existe, responder 200 sin reprocesar.
        $existing = WebhookInboxEntry::where('provider', $provider)
            ->where('external_event_id', $externalEventId)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Evento ya recibido.'], 200);
        }

        // 3) Registrar en el buzón (pendiente de procesar).
        $entry = WebhookInboxEntry::create([
            'provider' => $provider,
            'external_event_id' => $externalEventId,
            'signature_valid' => true,
            'processed_at' => null,
            'payload_hash' => $payloadHash,
            'created_at' => now(),
        ]);

        $log->record('webhook.received', [
            'direction' => 'inbound',
            'success' => true,
            'external_reference' => $provider.':'.$externalEventId,
        ]);

        // 4) Encolar el procesamiento (no bloquea la respuesta).
        ProcessInboundWebhook::dispatch($entry->id, $provider, (array) $request->json()->all());

        return response()->json(['message' => 'Recibido.'], 202);
    }
}
