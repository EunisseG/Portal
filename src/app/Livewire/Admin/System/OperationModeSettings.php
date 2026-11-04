<?php

namespace App\Livewire\Admin\System;

use App\Domain\Notifications\Models\Setting;
use App\Support\OperationMode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Interruptores del modo de operación: pagos y renovación de dominios, cada
 * uno manual (hoy) o automático (futuro). Se guardan en settings.
 */
class OperationModeSettings extends Component
{
    /** true = automático, false = manual. */
    public bool $paymentsAutomatic = false;

    public bool $domainRenewalAutomatic = false;

    public function mount(OperationMode $mode): void
    {
        Gate::authorize('integrations.manage');

        $this->paymentsAutomatic = $mode->paymentsAutomatic();
        $this->domainRenewalAutomatic = $mode->domainRenewalAutomatic();
    }

    public function save(): void
    {
        Gate::authorize('integrations.manage');

        Setting::put(
            OperationMode::KEY_PAYMENTS,
            $this->paymentsAutomatic ? OperationMode::MODE_AUTOMATIC : OperationMode::MODE_MANUAL,
        );
        Setting::put(
            OperationMode::KEY_DOMAIN_RENEWAL,
            $this->domainRenewalAutomatic ? OperationMode::MODE_AUTOMATIC : OperationMode::MODE_MANUAL,
        );

        session()->flash('status', 'Modo de operación actualizado.');
    }

    public function render(): View
    {
        return view('livewire.admin.system.operation-mode-settings')
            ->layout('layouts.app', ['area' => 'admin']);
    }
}
