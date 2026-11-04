<?php

namespace App\Integrations\Webhooks\Handlers;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Webhooks\WebhookHandler;
use Illuminate\Http\Request;

/**
 * Handler de webhooks de Mercado Pago.
 *
 * Mercado Pago firma con HMAC-SHA256. El header 'x-signature' trae
 * "ts=<timestamp>,v1=<hash>" y el manifiesto firmado es:
 *   id:<data.id>;request-id:<x-request-id>;ts:<ts>;
 * El secreto es la clave secreta del webhook configurada en el panel de
 * Mercado Pago (guardada cifrada como 'webhook_secret').
 *
 * Confirmación de pago: el payload solo trae el id; WORTEV sigue siendo la
 * fuente de verdad, así que aquí solo se registra el evento (el estado real
 * se consulta con getStatus si se requiere).
 */
class MercadoPagoWebhookHandler implements WebhookHandler
{
    public function provider(): string
    {
        return 'mercadopago';
    }

    public function verifySignature(Request $request): bool
    {
        $secret = $this->secret();

        if ($secret === null) {
            return false;
        }

        [$ts, $v1] = $this->parseSignatureHeader((string) $request->header('x-signature', ''));

        if ($ts === null || $v1 === null) {
            return false;
        }

        $dataId = $this->dataId($request);
        $requestId = (string) $request->header('x-request-id', '');

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $v1);
    }

    public function externalEventId(Request $request): string
    {
        // id del recurso notificado (pago). Sirve para idempotencia.
        $id = $this->dataId($request);

        return $id !== '' ? $id : (string) $request->header('x-request-id', '');
    }

    /**
     * Obtiene data.id de forma robusta. PHP convierte el punto de la clave de
     * query en guion bajo (data.id -> data_id); también puede llegar anidado
     * en el cuerpo JSON como data->id.
     */
    private function dataId(Request $request): string
    {
        return (string) (
            $request->query('data_id')          // query "data.id=..." (PHP => data_id)
            ?? $request->input('data.id')        // cuerpo JSON anidado {"data":{"id":...}}
            ?? $request->query('data.id')        // por si el servidor lo preserva
            ?? ''
        );
    }

    public function process(array $payload): void
    {
        // Solo nos interesan notificaciones de pago.
        $type = $payload['type'] ?? $payload['topic'] ?? null;
        if ($type !== null && ! in_array($type, ['payment', 'payment.updated', 'payment.created'], true)) {
            return;
        }

        // Resuelve la referencia externa (= folio de la orden) y el estado.
        // MercadoPago entrega el id del pago; se consulta el recurso para
        // obtener external_reference y status. En entornos sin red (fake) o si
        // el payload ya trae estos datos, se usan directamente.
        [$externalReference, $status, $paymentId] = $this->resolvePayment($payload);

        if ($externalReference === null) {
            return; // no se pudo resolver a qué orden corresponde
        }

        $order = \App\Domain\Billing\Models\PaymentOrder::where('external_reference', $externalReference)->first();

        if (! $order) {
            return; // la orden no existe (o es de otro sistema): no hacemos nada
        }

        // Solo conciliamos pagos aprobados. WORTEV es la fuente de verdad:
        // markPaid es idempotente (no duplica el pago si ya está pagada).
        if (in_array($status, ['approved', 'paid', 'accredited'], true)) {
            app(\App\Domain\Billing\Services\PaymentOrderService::class)->markPaid($order, $paymentId);
        }
    }

    /**
     * Determina [external_reference, status, payment_id] del evento. Prioriza
     * los datos ya presentes en el payload; si no, intenta consultar el pago
     * en la pasarela a partir de data.id.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function resolvePayment(array $payload): array
    {
        // 1) Datos presentes en el propio payload (útil para pruebas y para
        //    integraciones que envían el recurso completo).
        $externalReference = $payload['external_reference']
            ?? data_get($payload, 'data.external_reference');
        $status = $payload['status'] ?? data_get($payload, 'data.status');
        $paymentId = (string) (data_get($payload, 'data.id') ?? $payload['id'] ?? '') ?: null;

        if ($externalReference !== null) {
            return [(string) $externalReference, $status !== null ? (string) $status : null, $paymentId];
        }

        // 2) Consulta el pago en la pasarela por su id (solo provider real).
        if ($paymentId !== null) {
            $gateway = app(\App\Integrations\Contracts\PaymentGateway::class);
            if (method_exists($gateway, 'getPayment')) {
                $result = $gateway->getPayment($paymentId);
                if ($result->success) {
                    return [
                        $result->data['external_reference'] ?? null,
                        $result->data['status'] ?? null,
                        $paymentId,
                    ];
                }
            }
        }

        return [null, null, $paymentId];
    }

    /**
     * @return array{0: ?string, 1: ?string} [ts, v1]
     */
    private function parseSignatureHeader(string $header): array
    {
        $ts = null;
        $v1 = null;

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$k, $v] = $pair;
            if (trim($k) === 'ts') {
                $ts = trim($v);
            } elseif (trim($k) === 'v1') {
                $v1 = trim($v);
            }
        }

        return [$ts, $v1];
    }

    private function secret(): ?string
    {
        $integration = Integration::where('key', 'mercadopago')->first();

        return $integration?->secrets()->where('key', 'webhook_secret')->first()?->reveal();
    }
}
