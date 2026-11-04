<?php

namespace App\Integrations\Mail;

use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\MailGateway;
use Illuminate\Support\Facades\Log;

/**
 * Implementación de pruebas del adaptador de correo. No envía correos reales:
 * registra la intención en el log. Se reemplazará por SmtpMailGateway cuando
 * se configure un proveedor real.
 */
class FakeMailGateway implements MailGateway
{
    public function send(string $to, string $subject, string $body, array $options = []): GatewayResult
    {
        Log::info('[FakeMailGateway] send', compact('to', 'subject', 'options'));

        return GatewayResult::ok('Correo simulado (fake) encolado.', ['to' => $to]);
    }

    public function sendTestEmail(string $to): GatewayResult
    {
        Log::info('[FakeMailGateway] sendTestEmail', compact('to'));

        return GatewayResult::ok('Correo de prueba simulado (fake) enviado.', ['to' => $to]);
    }
}
