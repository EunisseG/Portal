<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Clients\Models\Client;
use App\Domain\Notifications\Jobs\SendInvoiceAvailableNotification;
use App\Domain\Notifications\Models\Setting;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\MailGateway;
use App\Integrations\Contracts\WhatsAppGateway;
use App\Integrations\Mail\SmtpMailGateway;
use App\Integrations\Mail\SmtpSettings;
use App\Livewire\Admin\System\BrandingSettings;
use App\Models\User;
use App\Support\Branding;
use App\Support\BrandedEmail;
use App\Support\EmailTemplate;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Correos con la marca del portal: diseño HTML editable desde Apariencia.
 */
class BrandedEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    private function render(string $subject = 'Asunto', string $body = 'Hola', array $options = []): array
    {
        return app(BrandedEmail::class)->render($subject, $body, $options);
    }

    // ------------------------------------------------------------------
    // Renderizado
    // ------------------------------------------------------------------

    public function test_html_uses_brand_name_and_default_accent(): void
    {
        Setting::put(Branding::KEY_NAME, 'Acme Hosting');

        $mail = $this->render('Tu factura', "Hola Juan,\n\nTexto.");

        $this->assertStringContainsString('Acme Hosting', $mail['html']);
        $this->assertStringContainsString(EmailTemplate::DEFAULT_ACCENT, $mail['html']);
        $this->assertStringContainsString('Tu factura', $mail['html']);
        $this->assertStringContainsString('Hola Juan,', $mail['html']);
    }

    public function test_body_is_escaped_and_never_interpreted_as_html(): void
    {
        $mail = $this->render('Asunto', '<script>alert(1)</script> y <b>negrita</b>');

        $this->assertStringNotContainsString('<script>', $mail['html']);
        $this->assertStringNotContainsString('<b>negrita</b>', $mail['html']);
        $this->assertStringContainsString('&lt;script&gt;', $mail['html']);
    }

    public function test_urls_become_links_without_trailing_punctuation(): void
    {
        $mail = $this->render('Asunto', 'Entra a https://portal.test/acceso?x=1&y=2.');

        $this->assertStringContainsString('<a href="https://portal.test/acceso?x=1&amp;y=2"', $mail['html']);
    }

    public function test_paragraphs_and_line_breaks_are_preserved(): void
    {
        $mail = $this->render('Asunto', "Línea uno\nLínea dos\n\nOtro párrafo");

        $this->assertSame(2, substr_count($mail['html'], 'margin:0 0 14px 0;'));
        $this->assertStringContainsString('Línea uno<br>', $mail['html']);
    }

    public function test_cta_renders_button_and_is_added_to_plain_text(): void
    {
        $mail = $this->render('Asunto', 'Texto', ['cta' => ['label' => 'Ver mi factura', 'url' => 'https://portal.test/facturas']]);

        $this->assertStringContainsString('Ver mi factura', $mail['html']);
        $this->assertStringContainsString('href="https://portal.test/facturas"', $mail['html']);
        $this->assertStringContainsString('Ver mi factura: https://portal.test/facturas', $mail['text']);
    }

    public function test_cta_with_unsafe_url_is_ignored(): void
    {
        $mail = $this->render('Asunto', 'Texto', ['cta' => ['label' => 'Clic', 'url' => 'javascript:alert(1)']]);

        $this->assertStringNotContainsString('javascript:', $mail['html']);
        $this->assertStringNotContainsString('Clic', $mail['text']);
    }

    // ------------------------------------------------------------------
    // Cambios de marca se reflejan solos
    // ------------------------------------------------------------------

    public function test_logo_name_and_color_changes_apply_to_the_next_email(): void
    {
        $first = $this->render();
        $this->assertStringNotContainsString('<img', $first['html']); // sin logo: nombre en texto

        Setting::put(Branding::KEY_LOGO_PATH, 'branding/logo-uno.png');
        Setting::put(Branding::KEY_NAME, 'Marca Nueva');
        Setting::put(EmailTemplate::KEY_ACCENT, '#ff0000');

        $second = $this->render();
        $this->assertStringContainsString('logo-uno.png', $second['html']);
        $this->assertStringContainsString('Marca Nueva', $second['html']);
        $this->assertStringContainsString('#ff0000', $second['html']);

        // Y si se cambia otra vez, el siguiente correo ya lleva el nuevo logo.
        Setting::put(Branding::KEY_LOGO_PATH, 'branding/logo-dos.png');
        $third = $this->render();
        $this->assertStringContainsString('logo-dos.png', $third['html']);
        $this->assertStringNotContainsString('logo-uno.png', $third['html']);
    }

    public function test_logo_url_in_email_is_absolute(): void
    {
        Setting::put(Branding::KEY_LOGO_PATH, 'branding/logo.png');

        $this->assertMatchesRegularExpression('~<img src="https?://~', $this->render()['html']);
    }

    public function test_footer_is_editable_and_has_default(): void
    {
        $this->assertStringContainsString('enviado automáticamente', $this->render()['html']);

        Setting::put(EmailTemplate::KEY_FOOTER, 'Soporte: soporte@acme.test');

        $this->assertStringContainsString('Soporte: soporte@acme.test', $this->render()['html']);
    }

    public function test_button_text_contrasts_with_light_and_dark_accents(): void
    {
        $this->assertSame('#ffffff', EmailTemplate::contrastOn('#1e1b4b'));
        $this->assertSame('#0f172a', EmailTemplate::contrastOn('#fde68a'));
    }

    public function test_invalid_stored_color_falls_back_to_default(): void
    {
        Setting::put(EmailTemplate::KEY_ACCENT, 'rojo; background:url(x)');

        $this->assertSame(EmailTemplate::DEFAULT_ACCENT, app(EmailTemplate::class)->accentColor());
        $this->assertStringNotContainsString('url(x)', $this->render()['html']);
    }

    // ------------------------------------------------------------------
    // Envío real (transporte en memoria)
    // ------------------------------------------------------------------

    /** Gateway SMTP con un transporte en memoria para inspeccionar el mensaje. */
    private function gatewayWithArrayTransport(): object
    {
        $transport = new ArrayTransport;

        $gateway = new class(app(SmtpSettings::class), app(EmailTemplate::class), app(BrandedEmail::class), $transport) extends SmtpMailGateway {
            public function __construct(SmtpSettings $s, EmailTemplate $t, BrandedEmail $b, public ArrayTransport $transport)
            {
                parent::__construct($s, $t, $b);
            }

            protected function buildMailer(): Mailer
            {
                return new Mailer('array', app('view'), $this->transport, app('events'));
            }
        };

        return $gateway;
    }

    public function test_smtp_gateway_sends_html_with_plain_text_alternative(): void
    {
        $gateway = $this->gatewayWithArrayTransport();

        $result = $gateway->send('cliente@wortev.test', 'Tu factura', "Hola.\n\nGracias.", [
            'cta' => ['label' => 'Ver mi factura', 'url' => 'https://portal.test/facturas'],
        ]);

        $this->assertTrue($result->success);
        $email = $gateway->transport->messages()->first()->getOriginalMessage();
        $this->assertStringContainsString('Ver mi factura', $email->getHtmlBody());
        $this->assertStringContainsString('<table', $email->getHtmlBody());
        $this->assertStringContainsString('Hola.', $email->getTextBody());
        $this->assertStringContainsString('https://portal.test/facturas', $email->getTextBody());
    }

    public function test_smtp_gateway_sends_plain_text_when_design_is_disabled(): void
    {
        Setting::put(EmailTemplate::KEY_ENABLED, '0');
        $gateway = $this->gatewayWithArrayTransport();

        $gateway->send('cliente@wortev.test', 'Asunto', 'Solo texto');

        $email = $gateway->transport->messages()->first()->getOriginalMessage();
        $this->assertNull($email->getHtmlBody());
        $this->assertStringContainsString('Solo texto', $email->getTextBody());
    }

    // ------------------------------------------------------------------
    // Edición desde admin
    // ------------------------------------------------------------------

    public function test_admin_can_edit_email_design_and_it_is_saved(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BrandingSettings::class)
            ->set('emailAccent', '#0ea5e9')
            ->set('emailFooter', 'Pie personalizado')
            ->set('emailHtmlEnabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $template = app(EmailTemplate::class);
        $this->assertSame('#0ea5e9', $template->accentColor());
        $this->assertSame('Pie personalizado', $template->footerText());
        $this->assertTrue($template->enabled());
    }

    public function test_admin_can_turn_off_email_design(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BrandingSettings::class)
            ->set('emailHtmlEnabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(app(EmailTemplate::class)->enabled());
    }

    public function test_invalid_color_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BrandingSettings::class)
            ->set('emailAccent', 'azul')
            ->call('save')
            ->assertHasErrors('emailAccent');

        $this->assertSame(EmailTemplate::DEFAULT_ACCENT, app(EmailTemplate::class)->accentColor());
    }

    public function test_settings_page_shows_live_preview_with_edited_color(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BrandingSettings::class)
            ->set('emailAccent', '#16a34a')
            ->assertSee('Vista previa')
            ->assertSee('#16a34a');
    }

    // ------------------------------------------------------------------
    // Avisos nuevos usan botón de acción
    // ------------------------------------------------------------------

    public function test_invoice_notification_sends_cta_button_to_the_invoices_page(): void
    {
        $user = User::factory()->create(['email' => 'cliente@wortev.test']);
        $client = Client::factory()->create(['user_id' => $user->id, 'whatsapp' => null]);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'folio' => 'F-9']);

        $mail = Mockery::mock(MailGateway::class);
        $mail->shouldReceive('send')->once()->withArgs(function ($to, $subject, $body, $options) {
            return $to === 'cliente@wortev.test'
                && ($options['cta']['label'] ?? null) === 'Ver mi factura'
                && ($options['cta']['url'] ?? null) === route('client.invoices.index');
        })->andReturn(GatewayResult::ok());
        $whatsapp = Mockery::mock(WhatsAppGateway::class);
        $whatsapp->shouldNotReceive('sendMessage');

        (new SendInvoiceAvailableNotification($invoice->id))->handle($mail, $whatsapp);
    }
}
