<?php

namespace App\Integrations\Providers\Whm;

use App\Domain\Services\Enums\ServerPanel;
use App\Domain\Services\Models\Server;
use App\Integrations\Contracts\HostingGateway;
use App\Integrations\Providers\Plesk\PleskHostingProvider;

/**
 * Elige el adaptador de hosting adecuado según el panel de control configurado
 * en el servidor (WHM/cPanel o Plesk). Ambos adaptadores implementan
 * HostingGateway y exponen testConnection().
 */
class HostingProviderFactory
{
    /**
     * Devuelve el proveedor de hosting para el servidor, o null si el servidor
     * no tiene una conexión de panel utilizable.
     */
    public static function forServer(Server $server): ?HostingGateway
    {
        if (! $server->hasPanel()) {
            return null;
        }

        return match ($server->panelDriver()) {
            ServerPanel::Whm => WhmHostingProvider::forServer($server),
            ServerPanel::Plesk => PleskHostingProvider::forServer($server),
            default => null,
        };
    }
}
