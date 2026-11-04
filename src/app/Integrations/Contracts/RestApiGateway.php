<?php

namespace App\Integrations\Contracts;

use App\Integrations\DTO\RestRequestSpec;

/**
 * Proveedor de API personalizada: ejecuta una petición HTTP REST controlada a
 * partir de una especificación validada. No ejecuta código arbitrario; solo
 * realiza solicitudes HTTP.
 */
interface RestApiGateway extends IntegrationProvider
{
    public function execute(RestRequestSpec $spec): GatewayResult;
}
