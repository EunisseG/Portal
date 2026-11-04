<?php

namespace App\Integrations\Contracts;

/**
 * Adaptador de hosting (WHM/cPanel). Prepara la integración futura sin
 * acoplar el dominio a un proveedor concreto. No realiza conexiones reales aún.
 */
interface HostingGateway
{
    /** Prueba de conexión con el panel del servidor. */
    public function testConnection(): GatewayResult;

    /**
     * @param  array<string, mixed>  $data  Datos de la cuenta (dominio, plan, usuario...).
     */
    public function createAccount(array $data): GatewayResult;

    public function suspend(string $username): GatewayResult;

    public function unsuspend(string $username): GatewayResult;

    public function changePackage(string $username, string $package): GatewayResult;

    public function getAccountInfo(string $username): GatewayResult;

    public function getUsage(string $username): GatewayResult;

    /**
     * Lista las cuentas de correo (buzones) de una cuenta de cPanel, con su
     * uso y cuota de disco. Solo lectura.
     *
     * En caso de éxito, GatewayResult::data['mailboxes'] es una lista de
     * arreglos normalizados: [
     *     'email' => string,
     *     'disk_used_mb' => float,
     *     'disk_quota_mb' => float|null,   // null = ilimitado
     *     'percent' => int|null,           // % usado (null si ilimitado)
     * ]
     */
    public function listEmailAccounts(string $cpanelUser): GatewayResult;
}
