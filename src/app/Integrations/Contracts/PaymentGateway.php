<?php

namespace App\Integrations\Contracts;

/**
 * Adaptador de proveedor de pagos. Preparado para uso futuro. En el alcance
 * inicial el cliente NO paga desde el portal; los pagos los registra el
 * administrador manualmente.
 */
interface PaymentGateway
{
    /**
     * @param  array<string, mixed>  $data  Datos del cargo (monto, moneda, referencia...).
     */
    public function createCharge(array $data): GatewayResult;

    public function getStatus(string $reference): GatewayResult;
}
