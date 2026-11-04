<?php

namespace App\Livewire\Admin\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationEventSubscription;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class IntegrationCatalog extends Component
{
    /**
     * Integraciones que existen en el registro (para el binding/pruebas) pero
     * NO se muestran en el catálogo. WHM se configura por servidor
     * (Sistema → Servidores / Planes), no como integración instalable.
     *
     * @var list<string>
     */
    private const HIDDEN_KEYS = ['whm'];

    public function mount(): void
    {
        Gate::authorize('integrations.manage');
    }

    /** Instala una integración a partir de su manifiesto (crea la fila configurable). */
    public function install(string $key, IntegrationRegistry $registry): void
    {
        Gate::authorize('integrations.manage');

        $manifest = $registry->manifest($key);
        abort_unless($manifest !== null, 404);

        // Integraciones ocultas no son instalables desde el catálogo.
        abort_if(in_array($key, self::HIDDEN_KEYS, true), 404);

        // Idempotente: si ya existe, no la duplica.
        if (Integration::where('key', $key)->exists()) {
            session()->flash('error', 'Esa integración ya está instalada.');

            return;
        }

        $integration = Integration::create([
            'key' => $manifest->key,
            'name' => $manifest->name,
            'capability' => $manifest->capability->value,
            'is_enabled' => false,
            'status' => 'not_configured',
        ]);

        // Suscribe (desactivadas) los eventos que soporta el manifiesto.
        foreach ($manifest->events as $eventName) {
            IntegrationEventSubscription::create([
                'integration_id' => $integration->id,
                'event_name' => $eventName,
                'is_enabled' => false,
            ]);
        }

        session()->flash('status', "Integración {$manifest->name} instalada. Configúrala para activarla.");
        $this->redirect(route('admin.integrations.configure', $integration), navigate: true);
    }

    public function render(IntegrationRegistry $registry): View
    {
        $installedKeys = Integration::pluck('key')->all();

        // Se ocultan del catálogo las integraciones marcadas (p. ej. WHM, que
        // se configura por servidor). El registro las conserva para el binding.
        $manifests = $registry->catalog()
            ->reject(fn ($manifest) => in_array($manifest->key, self::HIDDEN_KEYS, true))
            ->values();

        return view('livewire.admin.integrations.integration-catalog', [
            'manifests' => $manifests,
            'installedKeys' => $installedKeys,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
