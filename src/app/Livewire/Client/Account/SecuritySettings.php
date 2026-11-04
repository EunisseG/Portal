<?php

namespace App\Livewire\Client\Account;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Sección "Ajustes de Seguridad" de Mi Cuenta: gestión de la autenticación en
 * dos pasos (2FA). Los formularios postean a las rutas de Fortify; este
 * componente solo provee el chasis con el sidebar.
 */
class SecuritySettings extends Component
{
    public function render(): View
    {
        return view('livewire.client.account.security-settings', [
            'user' => auth()->user(),
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
