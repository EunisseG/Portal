<?php

namespace App\Integrations\Providers\MercadoPago;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Proveedor FAKE de Mercado Pago para desarrollo y pruebas (sin red).
 */
class FakeMercadoPagoProvider implements PaymentGateway, IntegrationProvider
{
    public function __construct(protected readonly ?Integration $integration = null) {}

    public function key(): string
    {
        return 'mercadopago';
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
        return GatewayResult::ok('Conexión simulada con Mercado Pago (fake).');
    }

    public function createCharge(array $data): GatewayResult
    {
        Log::info('[FakeMercadoPagoProvider] createCharge', $data);

        $reference = (string) ($data['reference'] ?? 'MP-FAKE-'.Str::upper(Str::random(8)));

        return GatewayResult::ok('Preferencia simulada creada (fake).', [
            'reference' => $reference,
            'preference_id' => 'pref_'.Str::random(12),
            'checkout_url' => 'https://sandbox.mercadopago.com/checkout/'.$reference,
        ]);
    }

    public function getStatus(string $reference): GatewayResult
    {
        return GatewayResult::ok('Estado simulado (fake).', [
            'reference' => $reference,
            'status' => 'pending',
        ]);
    }
}
