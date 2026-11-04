<?php

namespace App\Integrations\Providers\Openpay;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Proveedor FAKE de Openpay para desarrollo y pruebas (sin red).
 */
class FakeOpenpayProvider implements PaymentGateway, IntegrationProvider
{
    public function __construct(protected readonly ?Integration $integration = null) {}

    public function key(): string
    {
        return 'openpay';
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
        return GatewayResult::ok('Conexión simulada con Openpay (fake).');
    }

    public function createCharge(array $data): GatewayResult
    {
        Log::info('[FakeOpenpayProvider] createCharge', $data);

        $reference = (string) ($data['reference'] ?? 'OP-FAKE-'.Str::upper(Str::random(8)));

        return GatewayResult::ok('Cargo simulado creado (fake).', [
            'reference' => $reference,
            'charge_id' => 'tr'.Str::random(14),
            'status' => 'in_progress',
            'payment_reference' => (string) random_int(100000000000, 999999999999),
        ]);
    }

    public function getStatus(string $reference): GatewayResult
    {
        return GatewayResult::ok('Estado simulado (fake).', [
            'reference' => $reference,
            'status' => 'in_progress',
        ]);
    }
}
