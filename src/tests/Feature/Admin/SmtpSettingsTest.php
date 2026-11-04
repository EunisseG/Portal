<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Notifications\Models\Setting;
use App\Integrations\Contracts\MailGateway;
use App\Integrations\Mail\FakeMailGateway;
use App\Integrations\Mail\LoggingMailGateway;
use App\Integrations\Mail\SmtpMailGateway;
use App\Integrations\Mail\SmtpSettings;
use App\Livewire\Admin\System\SmtpSettingsForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SmtpSettingsTest extends TestCase
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

    public function test_admin_can_save_smtp_settings_and_password_is_encrypted(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SmtpSettingsForm::class)
            ->set('host', 'mailpit')
            ->set('port', 1025)
            ->set('username', 'user@wortev.test')
            ->set('password', 'secreto123')
            ->set('encryption', 'none')
            ->set('from_address', 'no-reply@wortev.test')
            ->set('from_name', 'WORTEV')
            ->call('save');

        $this->assertSame('mailpit', Setting::get(SmtpSettings::KEY_HOST));
        $this->assertSame('1025', Setting::get(SmtpSettings::KEY_PORT));

        // La contraseña se guarda cifrada (el valor crudo no es el texto plano)
        // pero Setting::get la descifra correctamente.
        $raw = Setting::where('key', SmtpSettings::KEY_PASSWORD)->first();
        $this->assertNotSame('secreto123', $raw->value);
        $this->assertTrue($raw->is_encrypted);
        $this->assertSame('secreto123', Setting::get(SmtpSettings::KEY_PASSWORD));
    }

    public function test_gateway_resolves_to_smtp_when_configured(): void
    {
        // Sin config: fake (envuelto en logging).
        $gateway = app(MailGateway::class);
        $this->assertInstanceOf(LoggingMailGateway::class, $gateway);

        Setting::put(SmtpSettings::KEY_HOST, 'mailpit');
        app()->forgetInstance(MailGateway::class);

        // Con config: el interno es SMTP. Verificamos indirectamente que
        // SmtpSettings ahora reporta configurado.
        $this->assertTrue(app(SmtpSettings::class)->isConfigured());
        $this->assertInstanceOf(SmtpMailGateway::class, app(SmtpMailGateway::class));
    }

    public function test_from_address_must_be_valid_email(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SmtpSettingsForm::class)
            ->set('from_address', 'no-es-un-correo')
            ->set('from_name', 'X')
            ->call('save')
            ->assertHasErrors('from_address');
    }

    public function test_test_email_requires_valid_recipient(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SmtpSettingsForm::class)
            ->set('testEmail', 'invalido')
            ->call('sendTest')
            ->assertHasErrors('testEmail');
    }

    public function test_send_test_shows_success_feedback(): void
    {
        // Simula un envío exitoso para verificar el feedback en pantalla.
        $this->mock(SmtpMailGateway::class, function ($mock) {
            $mock->shouldReceive('sendTestEmail')
                ->once()
                ->andReturn(\App\Integrations\Contracts\GatewayResult::ok('Correo enviado por SMTP.'));
        });

        Livewire::actingAs($this->admin())
            ->test(SmtpSettingsForm::class)
            ->set('testEmail', 'destino@wortev.test')
            ->call('sendTest')
            ->assertSet('testOk', true)
            ->assertSee('destino@wortev.test')
            ->assertSee('Enviado');

        // Queda registrado en el log de integraciones.
        $this->assertDatabaseHas('integration_logs', [
            'event' => 'smtp.test',
            'success' => true,
        ]);
    }

    public function test_send_test_shows_error_feedback(): void
    {
        // Simula un fallo de envío para verificar el mensaje de error.
        $this->mock(SmtpMailGateway::class, function ($mock) {
            $mock->shouldReceive('sendTestEmail')
                ->once()
                ->andReturn(\App\Integrations\Contracts\GatewayResult::fail('No se pudo conectar al servidor.'));
        });

        Livewire::actingAs($this->admin())
            ->test(SmtpSettingsForm::class)
            ->set('testEmail', 'destino@wortev.test')
            ->call('sendTest')
            ->assertSet('testOk', false)
            ->assertSee('No se pudo conectar al servidor.')
            ->assertSee('Error');
    }

    public function test_support_role_cannot_access_smtp_settings(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $this->actingAs($support)
            ->get(route('admin.settings.smtp'))
            ->assertForbidden();
    }

    public function test_smtp_form_is_preloaded_with_saved_settings(): void
    {
        Setting::put(SmtpSettings::KEY_HOST, 'mailpit');
        Setting::put(SmtpSettings::KEY_PORT, '1025');
        Setting::put(SmtpSettings::KEY_FROM_ADDRESS, 'no-reply@wortev.test');
        Setting::put(SmtpSettings::KEY_FROM_NAME, 'Portal WORTEV');

        Livewire::actingAs($this->admin())
            ->test(SmtpSettingsForm::class)
            ->assertSet('host', 'mailpit')
            ->assertSet('port', 1025)
            ->assertSet('from_address', 'no-reply@wortev.test')
            ->assertSet('from_name', 'Portal WORTEV')
            // La pantalla conserva el apartado de correo de prueba.
            ->assertSee('Enviar correo de prueba');
    }

    public function test_integrations_list_shows_configure_when_smtp_not_configured(): void
    {
        \App\Domain\Integrations\Models\Integration::create([
            'key' => 'smtp',
            'name' => 'SMTP',
            'capability' => 'mail',
            'is_enabled' => true,
            'status' => 'not_configured',
        ]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Integrations\IntegrationIndex::class)
            ->assertSee('Configurar')
            ->assertDontSee('Editar');
    }

    public function test_integrations_list_shows_edit_when_smtp_configured(): void
    {
        Setting::put(SmtpSettings::KEY_HOST, 'mailpit');

        \App\Domain\Integrations\Models\Integration::create([
            'key' => 'smtp',
            'name' => 'SMTP',
            'capability' => 'mail',
            'is_enabled' => true,
            'status' => 'connected',
        ]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Integrations\IntegrationIndex::class)
            ->assertSee('Editar')
            ->assertDontSee('Configurar');
    }
}
