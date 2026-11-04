<?php

namespace App\Livewire\Client\Domains;

use App\Livewire\Client\Services\ServiceList;

/**
 * Punto de entrada "/client/dominios". La gestión de dominios se unificó dentro
 * de la página de Servicios mediante pestañas; esta clase reutiliza esa página
 * pero exige el permiso de dominios y abre directamente la pestaña de dominios.
 */
class DomainList extends ServiceList
{
    public function mount(): void
    {
        // Esta entrada es específica de dominios: requiere su permiso.
        abort_unless(auth()->user()->canClient('client.domains.view'), 403);

        $this->tab = 'domains';
    }
}
