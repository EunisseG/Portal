<?php

namespace App\Integrations\Contracts;

/**
 * Adaptador de notificaciones por WhatsApp. Se usará posteriormente para
 * recordatorios de vencimiento, notificaciones de tickets y avisos importantes.
 */
interface WhatsAppGateway
{
    /**
     * @param  array<string, mixed>  $params  Parámetros de la plantilla.
     */
    public function sendMessage(string $to, string $template, array $params = []): GatewayResult;
}
