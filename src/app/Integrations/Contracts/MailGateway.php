<?php

namespace App\Integrations\Contracts;

/**
 * Adaptador de correo. Permite desacoplar el envío de correo del proveedor
 * SMTP concreto. La configuración (host, puerto, usuario, contraseña, cifrado,
 * remitente) se resolverá desde el almacén de settings, no desde el código.
 */
interface MailGateway
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function send(string $to, string $subject, string $body, array $options = []): GatewayResult;

    /**
     * Envía un correo de prueba a la dirección indicada. Usado por la función
     * administrativa de "enviar correo de prueba".
     */
    public function sendTestEmail(string $to): GatewayResult;
}
