<?php

namespace App\Livewire\Client\Account;

use App\Domain\Clients\Services\ClientMemberService;
use App\Livewire\Client\Team\MemberManager;
use Illuminate\Contracts\View\View;

/**
 * Sección "Contactos/Sub-Cuentas" de Mi Cuenta. Reutiliza toda la lógica de
 * gestión de sub-usuarios del titular (MemberManager) y solo cambia la vista
 * para presentarla dentro del chasis con el sidebar de Mi Cuenta.
 */
class AccountContacts extends MemberManager
{
    public function render(): View
    {
        $labels = [
            'client.invoices.view' => 'Ver facturas',
            'client.proofs.upload' => 'Subir comprobantes de pago',
            'client.tickets.view' => 'Ver tickets',
            'client.tickets.create' => 'Crear tickets',
            'client.services.view' => 'Ver servicios',
            'client.domains.view' => 'Ver dominios',
        ];

        return view('livewire.client.account.contacts', [
            'members' => $this->client->members()->with('roles')->get(),
            'canAddMore' => $this->client->members()->count() < ClientMemberService::MAX_MEMBERS,
            'permissionLabels' => $labels,
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
