<?php

namespace App\Integrations\Contracts;

/**
 * Adaptador de notificaciones internas al equipo (Slack, etc.). A diferencia
 * de WhatsApp (notificaciones al cliente), esto avisa al equipo de WORTEV de
 * eventos internos: nuevos tickets, pagos, vencimientos, etc.
 */
interface NotificationGateway
{
    /**
     * Envía una notificación de texto a un canal interno.
     *
     * @param  array<string, mixed>  $options  Opciones opcionales (channel, username, etc.).
     */
    public function notify(string $message, array $options = []): GatewayResult;
}
