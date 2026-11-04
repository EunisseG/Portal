<?php

namespace Tests\Feature\Mail;

use App\Domain\Notifications\Models\EmailLog;
use App\Domain\Notifications\Models\Setting;
use App\Integrations\Mail\SmtpSettings;
use App\Providers\MailConfigServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_smtp_settings_are_applied_to_mail_config(): void
    {
        Setting::put(SmtpSettings::KEY_HOST, 'smtp.example.com');
        Setting::put(SmtpSettings::KEY_PORT, '465');
        Setting::put(SmtpSettings::KEY_USERNAME, 'user@example.com');
        Setting::put(SmtpSettings::KEY_PASSWORD, 'secret', encrypted: true);
        Setting::put(SmtpSettings::KEY_ENCRYPTION, 'ssl');
        Setting::put(SmtpSettings::KEY_FROM_ADDRESS, 'no-reply@example.com');
        Setting::put(SmtpSettings::KEY_FROM_NAME, 'Portal Prueba');

        // Re-ejecuta el boot del provider ahora que ya hay settings.
        (new MailConfigServiceProvider($this->app))->boot();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('user@example.com', config('mail.mailers.smtp.username'));
        $this->assertSame('secret', config('mail.mailers.smtp.password'));
        // ssl => esquema smtps (TLS implícito).
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('no-reply@example.com', config('mail.from.address'));
        $this->assertSame('Portal Prueba', config('mail.from.name'));
    }

    public function test_mail_config_is_untouched_when_smtp_not_configured(): void
    {
        // Sin host configurado: no se fuerza smtp (se respeta el mailer actual).
        config(['mail.default' => 'array']);

        (new MailConfigServiceProvider($this->app))->boot();

        $this->assertSame('array', config('mail.default'));
    }

    public function test_sent_mail_is_recorded_in_email_logs(): void
    {
        // Usa el transporte 'array' (envía de verdad a memoria y dispara
        // MessageSent, que es lo que registra en email_logs).
        config(['mail.default' => 'array']);

        // Asegura el listener de MessageSent registrado por el provider.
        (new MailConfigServiceProvider($this->app))->boot();

        Mail::raw('Cuerpo de prueba', function ($message) {
            $message->to('destino@example.com')->subject('Asunto de prueba');
        });

        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'destino@example.com',
            'subject' => 'Asunto de prueba',
            'success' => true,
        ]);

        $log = EmailLog::first();
        $this->assertStringContainsString('Cuerpo de prueba', (string) $log->body);
    }

    public function test_custom_gateway_mail_is_not_double_logged(): void
    {
        config(['mail.default' => 'array']);
        (new MailConfigServiceProvider($this->app))->boot();

        // Simula un correo marcado por el gateway custom (ya registrado por
        // LoggingMailGateway): el listener global debe ignorarlo.
        Mail::raw('Cuerpo marcado', function ($message) {
            $message->to('marcado@example.com')->subject('Marcado');
            $message->getHeaders()->addTextHeader('X-Wortev-Logged', '1');
        });

        $this->assertDatabaseMissing('email_logs', [
            'recipient' => 'marcado@example.com',
        ]);
    }
}
