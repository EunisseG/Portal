<?php

namespace App\Livewire\Admin\Servers;

use App\Domain\Services\Models\Plan;
use App\Domain\Services\Models\Server;
use App\Domain\Services\Services\ServerService;
use App\Integrations\Actions\RecordIntegrationLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ServerIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** Pestaña activa: 'servers' o 'plans'. Persistida en la URL. */
    #[Url(as: 'tab')]
    public string $tab = 'servers';

    /**
     * Resultado de la última prueba de conexión por servidor, para mostrarlo
     * junto a su fila. Forma: [serverId => ['ok' => bool, 'msg' => string]].
     *
     * @var array<int, array{ok: bool, msg: string}>
     */
    public array $testResults = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Server::class);
    }

    /** Cambia de pestaña sin recargar la página. */
    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['servers', 'plans'], true) ? $tab : 'servers';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $serverId, ServerService $servers): void
    {
        $server = Server::findOrFail($serverId);
        Gate::authorize('delete', Server::class);

        if ($server->services()->exists()) {
            session()->flash('error', 'No se puede eliminar: el servidor tiene servicios asociados.');

            return;
        }

        $servers->delete($server);
        session()->flash('status', 'Servidor eliminado.');
    }

    /**
     * Prueba la conexión con el panel del servidor y muestra el resultado.
     */
    public function testConnection(int $serverId, RecordIntegrationLog $log): void
    {
        Gate::authorize('update', Server::class);

        $server = Server::findOrFail($serverId);
        $hosting = \App\Integrations\Providers\Whm\HostingProviderFactory::forServer($server);

        if (! $hosting) {
            $this->testResults[$serverId] = ['ok' => false, 'msg' => 'Sin conexión de panel configurada.'];
            $log->record('server.connection_test', [
                'success' => false,
                'message' => $server->name.': sin conexión de panel configurada.',
                'internal_reference' => 'server:'.$server->id,
            ]);

            return;
        }

        $start = microtime(true);
        $result = $hosting->testConnection();
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        // Resultado junto a la fila del servidor (ver vista).
        $this->testResults[$serverId] = $result->success
            ? ['ok' => true, 'msg' => 'Conexión correcta con el panel.']
            : ['ok' => false, 'msg' => 'La prueba de conexión falló. '.$result->message];

        $log->record('server.connection_test', [
            'success' => $result->success,
            'duration_ms' => $durationMs,
            'message' => $server->name.': '.($result->success ? 'conexión correcta con el panel.' : 'falló. '.$result->message),
            'internal_reference' => 'server:'.$server->id,
        ]);
    }

    public function render(): View
    {
        $servers = null;
        $plans = null;

        if ($this->tab === 'plans') {
            $plans = Plan::query()
                ->with('prices')
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(10);
        } else {
            $servers = Server::query()
                ->withCount('services')
                ->when($this->search !== '', function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', "%{$this->search}%")
                            ->orWhere('hostname', 'like', "%{$this->search}%")
                            ->orWhere('ip_address', 'like', "%{$this->search}%");
                    });
                })
                ->orderBy('name')
                ->paginate(10);
        }

        return view('livewire.admin.servers.server-index', [
            'servers' => $servers,
            'plans' => $plans,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
