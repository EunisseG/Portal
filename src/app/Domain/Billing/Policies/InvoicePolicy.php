<?php

namespace App\Domain\Billing\Policies;

use App\Domain\Billing\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('invoices.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('invoices.manage');
    }

    /**
     * Descargar/ver el archivo de una factura concreta: un administrador con
     * permiso, o el cliente dueño de la factura.
     */
    public function download(User $user, Invoice $invoice): bool
    {
        if ($user->can('invoices.view')) {
            return true;
        }

        $account = $user->clientAccount();

        return $account && $account->id === $invoice->client_id
            && (! $user->isSubUser() || $user->can('client.invoices.view'));
    }
}
