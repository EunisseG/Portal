<?php

namespace App\Livewire\Admin\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class IntegrationConfigure extends Component
{
    public Integration $integration;

    /** @var array<string, string> Config NO sensible (visible). */
    public array $config = [];

    /** @var array<string, string> Secretos capturados (vacío = conservar actual). */
    public array $secrets = [];

    /** @var array<int, string> Eventos suscritos (activados). */
    public array $enabledEvents = [];

    public function mount(Integration $integration, IntegrationRegistry $registry): void
    {
        Gate::authorize('integrations.manage');
        $this->integration = $integration;

        $manifest = $registry->manifest($integration->key);

        // Precargar config visible.
        foreach ($manifest?->configFields ?? [] as $field) {
            $this->config[$field] = (string) ($integration->config[$field] ?? '');
        }

        // Los secretos NUNCA se precargan (solo se muestran enmascarados en la vista).
        foreach ($manifest?->secretFields ?? [] as $field) {
            $this->secrets[$field] = '';
        }

        $this->enabledEvents = $integration->events()->where('is_enabled', true)->pluck('event_name')->all();
    }

    public function save(IntegrationRegistry $registry): void
    {
        Gate::authorize('integrations.manage');

        $manifest = $registry->manifest($this->integration->key);

        // Guardar config no sensible.
        $config = $this->integration->config ?? [];
        foreach ($manifest?->configFields ?? [] as $field) {
            $config[$field] = $this->config[$field] ?? null;
        }
        $this->integration->update(['config' => $config]);

        // Guardar secretos SOLO si se escribió un valor nuevo (cifrado).
        foreach ($manifest?->secretFields ?? [] as $field) {
            $value = trim($this->secrets[$field] ?? '');
            if ($value !== '') {
                IntegrationSecret::putFor($this->integration, $field, $value);
                $this->secrets[$field] = ''; // no conservar el valor en el componente
            }
        }

        session()->flash('status', 'Configuración guardada.');
    }

    public function saveEvents(): void
    {
        Gate::authorize('integrations.manage');

        foreach ($this->integration->events as $subscription) {
            $subscription->update([
                'is_enabled' => in_array($subscription->event_name, $this->enabledEvents, true),
            ]);
        }

        session()->flash('status', 'Suscripciones de eventos actualizadas.');
    }

    public function render(IntegrationRegistry $registry): View
    {
        $manifest = $registry->manifest($this->integration->key);

        // Mapa field => valor enmascarado del secreto ya guardado (para la UI).
        $savedSecrets = $this->integration->secrets()->get()
            ->mapWithKeys(fn ($s) => [$s->key => $s->masked()])
            ->all();

        return view('livewire.admin.integrations.integration-configure', [
            'manifest' => $manifest,
            'subscriptions' => $this->integration->events()->get(),
            'savedSecrets' => $savedSecrets,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
