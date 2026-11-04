<?php

namespace App\Domain\Billing\Policies;

use App\Models\User;

class PaymentPolicy
{
    /** Ver pagos registrados. */
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    /** Registrar un pago. */
    public function create(User $user): bool
    {
        return $user->can('payments.record');
    }
}
