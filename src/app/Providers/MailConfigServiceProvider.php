<?php

namespace App\Providers;

use App\Domain\Notifications\Models\EmailLog;
use App\Integrations\Mail\SmtpSettings;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Unifica el correo de la aplicación con la configuración SMTP del panel.
 *
 * Problema que resuelve: las notificaciones estándar de Laravel (verificación
 * de email, restablecer contraseña, etc.) usan el facade Mail con el mailer de
 * MAIL_MAILER, ajeno al SMTP configurado desde el panel. Este provider toma los
 * ajustes guardados (tabla settings) y los vuelca a config('mail') en tiempo de
 * ejecución, de modo que TODO el correo sale por el SMTP del panel. Además
 * registra cada mensaje realmente enviado en email_logs (evento MessageSent),
 * para que "Registros de correos enviados" refleje también estos envíos.
 */
class MailConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // La BD puede no estar lista (p. ej. durante migraciones iniciales).
        if (! $this->settingsReady()) {
            return;
        }

        $settings = $this->app->make(SmtpSettings::class);

        if ($settings->isConfigured()) {
            $this->applySmtpConfig($settings);
        }

        // Registra en email_logs cada correo efectivamente enviado por el
        // facade Mail / notificaciones (verificación incluida).
        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $this->logSentMessage($event);
        });
    }

    /** ¿Existe la tabla settings? (evita romper en instalaciones nuevas). */
    private function settingsReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Vuelca el SMTP del panel a config('mail') y lo fija como mailer por defecto. */
    private function applySmtpConfig(SmtpSettings $settings): void
    {
        // Esta versión de Laravel usa 'scheme' para el transporte SMTP: Symfony
        // deriva el cifrado del esquema ('smtps' = TLS implícito/SSL; 'smtp' =
        // STARTTLS si el servidor lo ofrece). Mapeamos igual que SmtpMailGateway.
        $scheme = $settings->encryption() === 'ssl' ? 'smtps' : 'smtp';

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.host' => $settings->host(),
            'mail.mailers.smtp.port' => $settings->port(),
            'mail.mailers.smtp.username' => $settings->username(),
            'mail.mailers.smtp.password' => $settings->password(),
            // Si el SMTP no responde (puerto bloqueado, host caído) se falla
            // pronto en lugar de colgar la petición hasta el 504 de nginx.
            'mail.mailers.smtp.timeout' => SmtpSettings::TIMEOUT_SECONDS,
            'mail.from.address' => $settings->fromAddress(),
            'mail.from.name' => $settings->fromName(),
        ]);
    }

    /** Escribe en email_logs el mensaje enviado (destinatarios, asunto, cuerpo). */
    private function logSentMessage(MessageSent $event): void
    {
        try {
            $message = $event->sent->getSymfonySentMessage()->getOriginalMessage();

            // Si el correo ya lo registró LoggingMailGateway (ruta del
            // MailGateway custom), no lo dupliquemos.
            if ($message->getHeaders()->has('X-Wortev-Logged')) {
                return;
            }

            // Solo registramos correos "normales" (Symfony Email).
            if (! $message instanceof \Symfony\Component\Mime\Email) {
                return;
            }

            $recipients = [];
            foreach ($message->getTo() as $address) {
                $recipients[] = $address->getAddress();
            }

            EmailLog::create([
                'recipient' => implode(', ', $recipients) ?: '(desconocido)',
                'subject' => $message->getSubject() ?: '(sin asunto)',
                'body' => $this->extractBody($message),
                'context' => ['mailer' => config('mail.default')],
                'success' => true,
                'error' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Registrar el correo no debe romper el envío; se traza y sigue.
            \Illuminate\Support\Facades\Log::warning('[MailConfig] no se pudo registrar el correo enviado', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Extrae un cuerpo legible del email (texto plano o HTML). */
    private function extractBody(\Symfony\Component\Mime\Email $email): ?string
    {
        $text = $email->getTextBody();
        if (is_string($text) && $text !== '') {
            return $text;
        }

        $html = $email->getHtmlBody();

        return is_string($html) ? $html : null;
    }
}
