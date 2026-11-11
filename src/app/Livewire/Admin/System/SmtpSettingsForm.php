<?php

namespace App\Livewire\Admin\System;

use App\Domain\Notifications\Models\Setting;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Integrations\Contracts\MailGateway;
use App\Integrations\Mail\SmtpMailGateway;
use App\Integrations\Mail\SmtpSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class SmtpSettingsForm extends Component
{
    public string $host = '';

    public int $port = 587;

    public string $username = '';

    public string $password = '';

    public string $encryption = 'tls';

    public string $from_address = '';

    public string $from_name = '';

    public string $testEmail = '';

    /** Resultado del último envío de prueba (null = aún no se ha probado). */
    public ?bool $testOk = null;

    /** Mensaje del último envío de prueba, mostrado junto al botón. */
    public string $testStatus = '';

    public function mount(SmtpSettings $settings): void
    {
        Gate::authorize('settings.manage');

        $this->host = (string) $settings->host();
        $this->port = $settings->port();
        $this->username = (string) $settings->username();
        // La contraseña no se rellena por seguridad; dejar vacío conserva la actual.
        $this->encryption = $settings->encryption() ?? 'tls';
        $this->from_address = $settings->fromAddress();
        $this->from_name = $settings->fromName();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        Gate::authorize('settings.manage');

        $data = $this->validate();

        Setting::put(SmtpSettings::KEY_HOST, $data['host'] ?: null);
        Setting::put(SmtpSettings::KEY_PORT, (string) $data['port']);
        Setting::put(SmtpSettings::KEY_USERNAME, $data['username'] ?: null);

        // Solo actualizar la contraseña si se escribió una nueva (cifrada).
        if ($data['password'] !== '') {
            Setting::put(SmtpSettings::KEY_PASSWORD, $data['password'], encrypted: true);
        }

        Setting::put(SmtpSettings::KEY_ENCRYPTION, $data['encryption'] === 'none' ? null : $data['encryption']);
        Setting::put(SmtpSettings::KEY_FROM_ADDRESS, $data['from_address']);
        Setting::put(SmtpSettings::KEY_FROM_NAME, $data['from_name']);

        $this->password = '';
        session()->flash('status', 'Configuración SMTP guardada.');
    }

    public function sendTest(RecordIntegrationLog $log): void
    {
        Gate::authorize('settings.manage');

        $this->validateOnly('testEmail', ['testEmail' => ['required', 'email']]);

        // Fuerza el envío por SMTP con la config guardada (independiente del
        // binding, para probar aunque aún no esté "configurado" globalmente).
        $gateway = app(SmtpMailGateway::class);
        $start = microtime(true);
        $result = $gateway->sendTestEmail($this->testEmail);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        // El resultado se muestra junto al botón de prueba (ver vista), para
        // que el admin lo vea sin tener que subir al inicio de la página.
        $this->testOk = $result->success;
        $this->testStatus = $result->success
            ? 'Correo de prueba enviado a '.$this->testEmail.'.'
            : ($result->message ?: 'No se pudo enviar el correo de prueba.');

        // Deja constancia en el registro de integraciones.
        $log->record('smtp.test', [
            'success' => $result->success,
            'duration_ms' => $durationMs,
            'message' => $result->success
                ? 'Correo de prueba enviado a '.$this->testEmail.'.'
                : $result->message,
        ]);
    }

    public function render(SmtpSettings $settings): View
    {
        return view('livewire.admin.system.smtp-settings-form', [
            'isConfigured' => $settings->isConfigured(),
            'hasPassword' => ! empty($settings->password()),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
