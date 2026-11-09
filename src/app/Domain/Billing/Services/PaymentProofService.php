<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\PaymentProofStatus;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Models\PaymentProof;
use App\Domain\Notifications\Jobs\SendPaymentProofNotification;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;

/**
 * Crea y revisa comprobantes de pago. Centraliza el guardado (cliente y admin),
 * la aprobación (que puede marcar pagada su orden) y el rechazo, y dispara el
 * aviso al ejecutivo de ventas cuando entra un comprobante nuevo.
 */
class PaymentProofService
{
    /** Disco privado donde se guardan los archivos de comprobante. */
    public const DISK = 'local';

    public function __construct(private readonly PaymentOrderService $orders) {}

    /**
     * Sube un comprobante a nombre de un cliente desde el admin. Puede ligarse
     * a una orden; si se pide, se aprueba de inmediato (marca la orden pagada).
     *
     * @param  array{
     *     client_id:int, payment_order:?PaymentOrder, file:UploadedFile,
     *     amount:float|string, method:string, paid_at:?string, reference:?string,
     *     approve:bool, reviewed_by:?int
     * }  $data
     */
    public function uploadByAdmin(array $data): PaymentProof
    {
        $order = $data['payment_order'];
        $amountCents = Money::fromDecimal($data['amount'])->amountInCents;

        $proof = $this->store([
            'client_id' => $data['client_id'],
            'payment_order_id' => $order?->id,
            'file' => $data['file'],
            'amount_cents' => $amountCents,
            'currency' => $order?->currency ?? Money::DEFAULT_CURRENCY,
            'method' => $data['method'],
            'paid_at' => $data['paid_at'],
            'reference' => $data['reference'],
        ]);

        if ($data['approve']) {
            $proof->forceFill([
                'status' => PaymentProofStatus::Approved->value,
                'reviewed_by' => $data['reviewed_by'],
                'reviewed_at' => now(),
            ])->save();

            if ($order) {
                $this->markOrderPaid($proof, $order, $data['reviewed_by']);
            }

            // Aprobado en el acto: no hace falta avisar "pendiente de revisión".
            return $proof;
        }

        $this->notifySalesRep($proof);

        return $proof;
    }

    /**
     * Sube un comprobante del propio cliente (desde el portal), ligado a su
     * orden. Queda en revisión y avisa al ejecutivo.
     */
    public function uploadByClient(PaymentOrder $order, UploadedFile $file, string $method, ?string $paidAt, ?string $reference): PaymentProof
    {
        $proof = $this->store([
            'client_id' => $order->client_id,
            'payment_order_id' => $order->id,
            'file' => $file,
            'amount_cents' => (int) $order->amount_cents,
            'currency' => $order->currency ?? Money::DEFAULT_CURRENCY,
            'method' => $method,
            'paid_at' => $paidAt,
            'reference' => $reference,
        ]);

        $this->notifySalesRep($proof);

        return $proof;
    }

    /**
     * Aprueba un comprobante. Si está ligado a una orden y quien aprueba puede
     * registrar pagos, marca la orden pagada (genera la factura y registra el
     * pago). Devuelve un mensaje para la UI.
     */
    public function approve(PaymentProof $proof, ?User $reviewer): string
    {
        $proof->forceFill([
            'status' => PaymentProofStatus::Approved->value,
            'review_note' => null,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
        ])->save();

        if ($proof->paymentOrder) {
            if (! $reviewer?->can('payments.record')) {
                return 'Comprobante aprobado. No tienes permiso para marcar la orden como pagada.';
            }

            $this->markOrderPaid($proof, $proof->paymentOrder, $reviewer->id);

            return 'Comprobante aprobado y orden marcada como pagada.';
        }

        return 'Comprobante aprobado.';
    }

    public function reject(PaymentProof $proof, string $note, ?User $reviewer): void
    {
        $proof->forceFill([
            'status' => PaymentProofStatus::Rejected->value,
            'review_note' => $note,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
        ])->save();
    }

    /** Marca la orden del comprobante como pagada (idempotente en el servicio de órdenes). */
    private function markOrderPaid(PaymentProof $proof, PaymentOrder $order, ?int $reviewerId): void
    {
        $this->orders->markPaid($order, null, [
            'method' => $proof->method?->value ?? 'other',
            'paid_at' => ($proof->paid_at ?? now())->toDateString(),
            'reference' => $proof->reference,
            'notes' => 'Pago con comprobante aprobado.',
            'payment_proof_id' => $proof->id,
            'recorded_by' => $reviewerId,
        ]);
    }

    /**
     * Guarda el archivo y crea el registro del comprobante en estado Pendiente.
     *
     * @param  array{
     *     client_id:int, payment_order_id:?int, file:UploadedFile,
     *     amount_cents:int, currency:string, method:string,
     *     paid_at:?string, reference:?string
     * }  $data
     */
    private function store(array $data): PaymentProof
    {
        $path = $data['file']->store('payment-proofs/'.$data['client_id'], self::DISK);

        return PaymentProof::create([
            'client_id' => $data['client_id'],
            'invoice_id' => null,
            'payment_order_id' => $data['payment_order_id'],
            'file_path' => $path,
            'original_name' => $data['file']->getClientOriginalName(),
            'amount_cents' => $data['amount_cents'],
            'currency' => $data['currency'],
            'method' => $data['method'],
            'paid_at' => $data['paid_at'],
            'reference' => $data['reference'],
            'status' => PaymentProofStatus::Pending->value,
        ]);
    }

    /** Avisa al ejecutivo de ventas que entró un comprobante nuevo. */
    private function notifySalesRep(PaymentProof $proof): void
    {
        SendPaymentProofNotification::dispatch($proof->id)->afterCommit();
    }
}
