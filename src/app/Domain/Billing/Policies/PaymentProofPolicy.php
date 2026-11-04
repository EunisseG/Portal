<?php

namespace App\Domain\Billing\Policies;

use App\Domain\Billing\Models\PaymentProof;
use App\Models\User;

class PaymentProofPolicy
{
    /** Ver el listado (admin/staff con permiso). */
    public function viewAny(User $user): bool
    {
        return $user->can('payment_proofs.manage');
    }

    /** Revisar/aprobar/rechazar. */
    public function manage(User $user): bool
    {
        return $user->can('payment_proofs.manage');
    }

    /**
     * Descargar el archivo del comprobante: un gestor con permiso, o el cliente
     * dueño del comprobante.
     */
    public function download(User $user, PaymentProof $proof): bool
    {
        if ($user->can('payment_proofs.manage')) {
            return true;
        }

        $account = $user->clientAccount();

        return $account && $account->id === $proof->client_id;
    }
}
