<?php

namespace App\Integrations\Mail;

use App\Domain\Notifications\Models\EmailLog;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\MailGateway;

/**
 * Decorator del MailGateway: registra cada envío en la tabla email_logs y
 * delega el envío real al gateway interno. Funciona con cualquier
 * implementación (fake hoy, SMTP en el futuro) sin acoplar el registro.
 */
class LoggingMailGateway implements MailGateway
{
    public function __construct(private readonly MailGateway $inner) {}

    public function send(string $to, string $subject, string $body, array $options = []): GatewayResult
    {
        $result = $this->inner->send($to, $subject, $body, $options);

        EmailLog::create([
            'recipient' => $to,
            'subject' => $subject,
            'body' => $body,
            'context' => $options ?: null,
            'success' => $result->success,
            'error' => $result->success ? null : $result->message,
            'sent_at' => now(),
        ]);

        return $result;
    }

    public function sendTestEmail(string $to): GatewayResult
    {
        $result = $this->inner->sendTestEmail($to);

        EmailLog::create([
            'recipient' => $to,
            'subject' => 'Correo de prueba',
            'body' => null,
            'context' => ['type' => 'test'],
            'success' => $result->success,
            'error' => $result->success ? null : $result->message,
            'sent_at' => now(),
        ]);

        return $result;
    }
}
