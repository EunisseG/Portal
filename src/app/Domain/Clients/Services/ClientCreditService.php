<?php

namespace App\Domain\Clients\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Clients\Enums\CreditMovementType;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\ClientCreditMovement;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Gestión del saldo a favor (crédito) de un cliente. Cada ajuste queda como un
 * movimiento en el historial; el saldo del cliente es la suma de los mismos y
 * nunca queda negativo.
 */
class ClientCreditService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Aplica un ajuste al saldo del cliente y lo registra en el historial.
     *
     * @param  int  $amountCents  Monto del movimiento (positivo). El signo lo da el tipo.
     */
    public function adjust(
        Client $client,
        CreditMovementType $type,
        int $amountCents,
        string $reason,
        ?int $userId = null,
    ): ClientCreditMovement {
        $amountCents = abs($amountCents);

        return DB::transaction(function () use ($client, $type, $amountCents, $reason, $userId): ClientCreditMovement {
            $currency = $client->credit_currency ?: Money::DEFAULT_CURRENCY;
            $oldBalance = (int) ($client->credit_balance_cents ?? 0);

            // El saldo nunca baja de cero: un cargo mayor al saldo lo deja en 0.
            $newBalance = max(0, $oldBalance + ($type->sign() * $amountCents));

            $movement = ClientCreditMovement::create([
                'client_id' => $client->id,
                'type' => $type->value,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'balance_after_cents' => $newBalance,
                'reason' => $reason,
                'created_by' => $userId ?? auth()->id(),
            ]);

            $client->update([
                'credit_balance_cents' => $newBalance,
                'credit_currency' => $currency,
            ]);

            $this->audit->log(
                AuditAction::ClientCreditAdjusted,
                $client,
                ['credit_balance_cents' => $oldBalance],
                [
                    'credit_balance_cents' => $newBalance,
                    'type' => $type->value,
                    'amount_cents' => $amountCents,
                    'reason' => $reason,
                ],
            );

            return $movement;
        });
    }
}
