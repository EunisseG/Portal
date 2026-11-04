<?php

namespace App\Integrations\Providers\Namecheap;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\DomainGateway;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor FAKE de dominios (Namecheap) para desarrollo y pruebas.
 *
 * No hace llamadas de red: simula respuestas realistas y las registra en el
 * log, para poder recorrer los flujos del panel sin credenciales reales.
 */
class FakeNamecheapDomainProvider implements DomainGateway, IntegrationProvider
{
    public function __construct(protected readonly ?Integration $integration = null) {}

    public function key(): string
    {
        return 'domains';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Domains;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function testConnection(): GatewayResult
    {
        return GatewayResult::ok('Conexión simulada correcta (fake).');
    }

    public function register(string $domain, int $years = 1): GatewayResult
    {
        Log::info('[FakeNamecheapDomainProvider] register', compact('domain', 'years'));

        return GatewayResult::ok('Dominio registrado (fake).', [
            'domain' => $domain,
            'expires_at' => now()->addYears($years)->toDateString(),
        ]);
    }

    public function renew(string $domain, int $years = 1): GatewayResult
    {
        Log::info('[FakeNamecheapDomainProvider] renew', compact('domain', 'years'));

        return GatewayResult::ok('Dominio renovado (fake).', [
            'domain' => $domain,
            'expires_at' => now()->addYears($years)->toDateString(),
        ]);
    }

    public function transfer(string $domain, array $data = []): GatewayResult
    {
        Log::info('[FakeNamecheapDomainProvider] transfer', compact('domain', 'data'));

        return GatewayResult::ok('Transferencia iniciada (fake).', compact('domain'));
    }

    public function getInfo(string $domain): GatewayResult
    {
        return GatewayResult::ok('Información simulada (fake).', [
            'domain' => $domain,
            'status' => 'active',
            'expires_at' => now()->addYear()->toDateString(),
        ]);
    }

    public function check(string ...$domains): GatewayResult
    {
        return GatewayResult::ok('Disponibilidad simulada (fake).', [
            'domains' => array_map(fn ($d) => ['domain' => $d, 'available' => true], $domains),
        ]);
    }
}
