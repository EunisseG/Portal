<?php

namespace App\Integrations\Contracts;

/**
 * Adaptador de proveedor de dominios. Prepara registro, renovación,
 * transferencia y consulta sin conectar un proveedor real todavía.
 */
interface DomainGateway
{
    public function register(string $domain, int $years = 1): GatewayResult;

    public function renew(string $domain, int $years = 1): GatewayResult;

    /**
     * @param  array<string, mixed>  $data  Datos de transferencia (auth code, etc.).
     */
    public function transfer(string $domain, array $data = []): GatewayResult;

    /**
     * Consulta información del dominio: estado y fecha de vencimiento.
     */
    public function getInfo(string $domain): GatewayResult;
}
