<?php

namespace App\Integrations\Providers\PayPal;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Proveedor FAKE de PayPal para desarrollo y pruebas (sin red).
 */
class FakePayPalProvider implements PaymentGateway, IntegrationProvider
{
    public function __construct(protected readonly ?Integration $integration = null) {}

    public function key(): string
    {
        return 'paypal';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Payments;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function testConnection(): GatewayResult
    {
        return GatewayResult::ok('Conexión simulada con PayPal (fake).');
    }

    public function createCharge(array $data): GatewayResult
    {
        Log::info('[FakePayPalProvider] createCharge', $data);

        $reference = (string) ($data['reference'] ?? 'PP-FAKE-'.Str::upper(Str::random(8)));
        $orderId = strtoupper(Str::random(17));

        return GatewayResult::ok('Orden simulada creada (fake).', [
            'reference' => $reference,
            'order_id' => $orderId,
            'status' => 'CREATED',
            'checkout_url' => 'https://www.sandbox.paypal.com/checkoutnow?token='.$orderId,
        ]);
    }

    public function getStatus(string $reference): GatewayResult
    {
        return GatewayResult::ok('Estado simulado (fake).', [
            'reference' => $reference,
            'status' => 'CREATED',
        ]);
    }
}
