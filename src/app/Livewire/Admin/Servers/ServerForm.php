<?php

namespace App\Livewire\Admin\Servers;

use App\Domain\Services\Enums\ServerPanel;
use App\Domain\Services\Models\Server;
use App\Domain\Services\Services\ServerService;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Integrations\Providers\Whm\HostingProviderFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ServerForm extends Component
{
    public ?Server $server = null;

    /**
     * Resultado de la última prueba de conexión ("Guardar y probar"), mostrado
     * junto al botón. null = aún no se ha probado.
     */
    public ?bool $testOk = null;

    public string $testStatus = '';

    public string $name = '';
    public string $hostname = '';
    public string $ip_address = '';
    public string $notes = '';
    public bool $is_active = true;

    // Conexión al panel de control.
    public string $panel = '';               // '' = sin conexión
    public string $panel_scheme = 'https';
    public string $panel_host = '';
    public ?int $panel_port = null;
    public string $panel_user = '';
    /** Token nuevo; vacío = conservar el actual (no se precarga por seguridad). */
    public string $panel_token = '';
    public bool $panel_verify_tls = true;
    public string $panel_user_prefix = '';
    public int $panel_password_length = 16;

    // Nameservers sugeridos para los dominios de este servidor.
    public string $ns1 = '';
    public string $ns2 = '';
    public string $ns3 = '';
    public string $ns4 = '';

    public function mount(?Server $server = null): void
    {
        if ($server && $server->exists) {
            Gate::authorize('update', Server::class);
            $this->server = $server;
            $this->name = $server->name;
            $this->hostname = (string) $server->hostname;
            $this->ip_address = (string) $server->ip_address;
            $this->notes = (string) $server->notes;
            $this->is_active = $server->is_active;
            $this->panel = $server->panel?->value ?? '';
            $this->panel_scheme = $server->panel_scheme ?: 'https';
            $this->panel_host = (string) $server->panel_host;
            $this->panel_port = $server->panel_port;
            $this->panel_user = (string) $server->panel_user;
            $this->panel_verify_tls = (bool) ($server->panel_verify_tls ?? true);
            $this->panel_user_prefix = (string) $server->panel_user_prefix;
            $this->panel_password_length = $server->panel_password_length ?: 16;
            $this->ns1 = (string) $server->ns1;
            $this->ns2 = (string) $server->ns2;
            $this->ns3 = (string) $server->ns3;
            $this->ns4 = (string) $server->ns4;
        } else {
            Gate::authorize('create', Server::class);
        }
    }

    public function isEditing(): bool
    {
        return $this->server !== null;
    }

    /** Sugerir el puerto por defecto al cambiar de gestor. */
    public function updatedPanel(string $value): void
    {
        $driver = ServerPanel::tryFrom($value);
        if ($driver && empty($this->panel_port)) {
            $this->panel_port = $driver->defaultPort();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'panel' => ['nullable', Rule::enum(ServerPanel::class)],
            'panel_scheme' => ['required_with:panel', 'in:https,http'],
            'panel_host' => ['nullable', 'required_with:panel', 'string', 'max:255'],
            'panel_port' => ['nullable', 'integer', 'between:1,65535'],
            'panel_user' => ['nullable', 'string', 'max:255'],
            'panel_token' => ['nullable', 'string', 'max:1000'],
            'panel_verify_tls' => ['boolean'],
            'panel_user_prefix' => ['nullable', 'string', 'max:50'],
            'panel_password_length' => ['integer', 'between:8,64'],
            'ns1' => ['nullable', 'string', 'max:255'],
            'ns2' => ['nullable', 'string', 'max:255'],
            'ns3' => ['nullable', 'string', 'max:255'],
            'ns4' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function persist(): Server
    {
        $data = $this->validate();

        $payload = [
            'name' => $data['name'],
            'hostname' => $data['hostname'] ?: null,
            'ip_address' => $data['ip_address'] ?: null,
            'notes' => $data['notes'] ?: null,
            'is_active' => $data['is_active'],
            'panel' => $data['panel'] ?: null,
            'panel_scheme' => $data['panel_scheme'] ?: 'https',
            'panel_host' => $data['panel_host'] ?: null,
            'panel_port' => $data['panel_port'] ?: null,
            'panel_user' => $data['panel_user'] ?: null,
            'panel_verify_tls' => $data['panel_verify_tls'],
            'panel_user_prefix' => $data['panel_user_prefix'] ?: null,
            'panel_password_length' => $data['panel_password_length'],
            'ns1' => $data['ns1'] ?: null,
            'ns2' => $data['ns2'] ?: null,
            'ns3' => $data['ns3'] ?: null,
            'ns4' => $data['ns4'] ?: null,
        ];

        // El token solo se actualiza si se escribió uno nuevo (se conserva el
        // actual cuando el campo queda vacío). Se cifra vía el cast del modelo.
        $newToken = trim($this->panel_token);
        if ($newToken !== '') {
            $payload['panel_token'] = $newToken;
        }

        $servers = app(ServerService::class);

        if ($this->isEditing()) {
            $servers->update($this->server, $payload);
        } else {
            $this->server = $servers->create($payload);
        }

        $this->panel_token = '';

        return $this->server;
    }

    public function save(): mixed
    {
        $this->persist();
        session()->flash('status', 'Servidor guardado.');

        return $this->redirect(route('admin.servers.index'), navigate: true);
    }

    /**
     * Guarda y prueba la conexión con el panel, mostrando el resultado sin salir
     * del formulario.
     */
    public function saveAndTest(RecordIntegrationLog $log): void
    {
        $server = $this->persist();
        $server->refresh();

        // Confirma el guardado (arriba) y muestra el resultado de la prueba
        // junto al botón, sin salir del formulario.
        session()->flash('status', 'Servidor guardado.');

        $hosting = HostingProviderFactory::forServer($server);

        if (! $hosting) {
            $this->testOk = null;
            $this->testStatus = 'Servidor guardado, pero no hay conexión de panel configurada para probar.';

            return;
        }

        $start = microtime(true);
        $result = $hosting->testConnection();
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $this->testOk = $result->success;
        $this->testStatus = $result->success
            ? 'Conexión correcta con el panel.'
            : 'La prueba de conexión falló: '.$result->message;

        $log->record('server.connection_test', [
            'success' => $result->success,
            'duration_ms' => $durationMs,
            'message' => $server->name.': '.($result->success ? 'conexión correcta con el panel.' : 'falló. '.$result->message),
            'internal_reference' => 'server:'.$server->id,
        ]);
    }

    public function render(): View
    {
        return view('livewire.admin.servers.server-form', [
            'panelOptions' => ServerPanel::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
