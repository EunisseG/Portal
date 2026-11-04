<?php

namespace App\Livewire\Admin\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Integrations\Mail\SmtpSettings;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class IntegrationIndex extends Component
{
    /**
     * Integraciones que no se listan en el panel (se configuran en otro lugar).
     * WHM se configura por servidor (Sistema → Servidores / Planes).
     *
     * @var list<string>
     */
    private const HIDDEN_KEYS = ['whm'];

    /**
     * Resultado de la última prueba de conexión por integración, para mostrarlo
     * junto a su fila. Forma: [integrationId => ['ok' => bool, 'msg' => string]].
     *
     * @var array<int, array{ok: bool, msg: string}>
     */
    public array $testResults = [];

    public function mount(): void
    {
        Gate::authorize('integrations.manage');
    }

    public function toggle(int $id): void
    {
        Gate::authorize('integrations.manage');

        $integration = Integration::findOrFail($id);
        $integration->update([
            'is_enabled' => ! $integration->is_enabled,
            'status' => ! $integration->is_enabled ? 'connected' : 'disabled',
        ]);

        session()->flash('status', $integration->is_enabled
            ? "Integración {$integration->name} activada."
            : "Integración {$integration->name} desactivada.");
    }

    public function uninstall(int $id): void
    {
        Gate::authorize('integrations.manage');

        $integration = Integration::findOrFail($id);
        $name = $integration->name;
        $integration->delete(); // cascade: secrets, eventos, referencias

        session()->flash('status', "Integración {$name} desinstalada.");
    }

    public function testConnection(int $id, IntegrationRegistry $registry, RecordIntegrationLog $log): void
    {
        Gate::authorize('integrations.manage');

        $integration = Integration::findOrFail($id);
        $provider = $registry->resolve($integration);

        if (! $provider) {
            $integration->update(['status' => 'error', 'last_error' => 'Proveedor no disponible.']);
            $this->testResults[$id] = ['ok' => false, 'msg' => 'No hay proveedor disponible para esta integración.'];
            $log->record('integration.connection_test', [
                'integration_id' => $integration->id,
                'success' => false,
                'message' => 'No hay proveedor disponible.',
            ]);

            return;
        }

        $start = microtime(true);
        $result = $provider->testConnection();
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $integration->update([
            'status' => $result->success ? 'connected' : 'error',
            'last_tested_at' => now(),
            'last_error' => $result->success ? null : $result->message,
        ]);

        // Resultado junto a la fila de la integración (ver vista).
        $this->testResults[$id] = $result->success
            ? ['ok' => true, 'msg' => 'Prueba de conexión correcta.']
            : ['ok' => false, 'msg' => 'Prueba de conexión falló: '.$result->message];

        $log->record('integration.connection_test', [
            'integration_id' => $integration->id,
            'success' => $result->success,
            'duration_ms' => $durationMs,
            'message' => $result->success ? 'Prueba de conexión correcta.' : $result->message,
        ]);
    }

    public function render(SmtpSettings $smtp): View
    {
        $integrations = Integration::query()
            ->whereNotIn('key', self::HIDDEN_KEYS)
            ->orderBy('name')
            ->get();

        return view('livewire.admin.integrations.integration-index', [
            'integrations' => $integrations,
            // La config SMTP vive en settings (no en la fila Integration), así
            // que su estado "configurado" se consulta aparte.
            'smtpConfigured' => $smtp->isConfigured(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
