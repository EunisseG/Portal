<?php

namespace App\Livewire\Client\Account;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Sección "Cambiar Contraseña" de Mi Cuenta. El formulario postea a Fortify
 * (user-password.update); este componente solo provee el chasis con sidebar.
 */
class ChangePassword extends Component
{
    public function render(): View
    {
        return view('livewire.client.account.change-password')
            ->layout('layouts.app', ['area' => 'client']);
    }
}
