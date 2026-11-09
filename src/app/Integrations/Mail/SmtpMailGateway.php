<?php

namespace App\Integrations\Mail;

use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\MailGateway;
use App\Support\BrandedEmail;
use App\Support\EmailTemplate;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

/**
 * Adaptador de correo real por SMTP. Construye el transporte en runtime a
 * partir de la configuración guardada en settings (host/puerto/credenciales).
 * Si el envío falla, devuelve un GatewayResult::fail sin lanzar excepción.
 */
class SmtpMailGateway implements MailGateway
{
    public function __construct(
        private readonly SmtpSettings $settings,
        private readonly EmailTemplate $template,
        private readonly BrandedEmail $branded,
    ) {}

    public function send(string $to, string $subject, string $body, array $options = []): GatewayResult
    {
        try {
            $mailer = $this->buildMailer();

            $from = $this->settings->fromAddress();
            $fromName = $this->settings->fromName();

            $callback = function ($message) use ($to, $subject, $from, $fromName) {
                $message->to($to)->subject($subject)->from($from, $fromName);
                // Marca para que el listener global de MessageSent no vuelva a
                // registrar este correo en email_logs: ya lo hace LoggingMailGateway.
                $message->getHeaders()->addTextHeader('X-Wortev-Logged', '1');
            };

            if ($this->template->enabled()) {
                // Correo con la marca del portal (HTML) y su versión en texto plano.
                $rendered = $this->branded->render($subject, $body, $options);

                $mailer->send(
                    ['html' => new HtmlString($rendered['html']), 'text' => new HtmlString($rendered['text'])],
                    [],
                    $callback,
                );
            } else {
                $mailer->raw($body, $callback);
            }

            return GatewayResult::ok('Correo enviado por SMTP.', ['to' => $to]);
        } catch (\Throwable $e) {
            Log::error('[SmtpMailGateway] send failed', ['to' => $to, 'error' => $e->getMessage()]);

            return GatewayResult::fail('No se pudo enviar el correo: '.$e->getMessage(), ['to' => $to]);
        }
    }

    public function sendTestEmail(string $to): GatewayResult
    {
        return $this->send(
            $to,
            'Correo de prueba · Portal WORTEV',
            "Este es un correo de prueba enviado desde el Portal WORTEV.\n\nSi lo recibes, la configuración SMTP funciona correctamente.",
        );
    }

    protected function buildMailer(): Mailer
    {
        $transport = $this->buildTransport();

        return new Mailer('smtp', app('view'), $transport, app('events'));
    }

    private function buildTransport(): TransportInterface
    {
        $scheme = $this->settings->encryption() === 'ssl' ? 'smtps' : 'smtp';

        $dsn = new Dsn(
            $scheme,
            $this->settings->host() ?? 'localhost',
            $this->settings->username() ?: null,
            $this->settings->password() ?: null,
            $this->settings->port(),
        );

        return (new EsmtpTransportFactory())->create($dsn);
    }
}
