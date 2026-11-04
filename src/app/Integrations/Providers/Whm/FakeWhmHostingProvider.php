<?php

namespace App\Integrations\Providers\Whm;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\HostingGateway;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor FAKE de hosting (WHM). No realiza llamadas reales: simula
 * respuestas realistas para desarrollo y pruebas.
 */
class FakeWhmHostingProvider implements HostingGateway, IntegrationProvider
{
    public function __construct(protected readonly ?Integration $integration = null) {}

    public function key(): string
    {
        return 'whm';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Hosting;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function testConnection(): GatewayResult
    {
        return GatewayResult::ok('Conexión con WHM simulada (fake).');
    }

    public function createAccount(array $data): GatewayResult
    {
        Log::info('[FakeWhmHostingProvider] createAccount', $data);
        $username = $data['username'] ?? ('u'.substr(md5(json_encode($data)), 0, 8));

        return GatewayResult::ok('Cuenta creada (fake).', [
            'username' => $username,
            'external_id' => $username,
        ]);
    }

    public function suspend(string $username): GatewayResult
    {
        return GatewayResult::ok('Cuenta suspendida (fake).', ['username' => $username]);
    }

    public function unsuspend(string $username): GatewayResult
    {
        return GatewayResult::ok('Cuenta reactivada (fake).', ['username' => $username]);
    }

    public function changePackage(string $username, string $package): GatewayResult
    {
        return GatewayResult::ok('Paquete cambiado (fake).', compact('username', 'package'));
    }

    public function getAccountInfo(string $username): GatewayResult
    {
        return GatewayResult::ok('Info simulada (fake).', ['username' => $username, 'status' => 'active']);
    }

    public function getUsage(string $username): GatewayResult
    {
        return GatewayResult::ok('Uso simulado (fake).', ['username' => $username, 'disk_used_mb' => 0]);
    }

    public function listEmailAccounts(string $cpanelUser): GatewayResult
    {
        // Buzones simulados para desarrollo/pruebas.
        return GatewayResult::ok('Correos simulados (fake).', [
            'mailboxes' => [
                ['email' => 'contacto@'.$cpanelUser.'.com', 'disk_used_mb' => 128.5, 'disk_quota_mb' => 1024.0, 'percent' => 13],
                ['email' => 'ventas@'.$cpanelUser.'.com', 'disk_used_mb' => 512.0, 'disk_quota_mb' => 1024.0, 'percent' => 50],
                ['email' => 'admin@'.$cpanelUser.'.com', 'disk_used_mb' => 42.0, 'disk_quota_mb' => null, 'percent' => null],
            ],
        ]);
    }
}
