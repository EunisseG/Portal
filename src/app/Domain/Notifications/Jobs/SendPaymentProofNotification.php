<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Billing\Models\PaymentProof;
use App\Domain\Sales\Models\SalesRep;
use App\Integrations\Contracts\MailGateway;
use App\Integrations\Contracts\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa al EJECUTIVO DE VENTAS que entró un comprobante de pago nuevo, por
 * correo y WhatsApp (a su teléfono). El ejecutivo se resuelve por el servicio
 * de la orden del comprobante. Si no hay ejecutivo (p. ej. el comprobante no
 * está ligado a una orden con servicio), no envía nada.
 */
class SendPaymentProofNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $proofId)
    {
        $this->onQueue('integrations');
    }

    public function handle(MailGateway $mail, WhatsAppGateway $whatsapp): void
    {
        $proof = PaymentProof::with(['client.user', 'paymentOrder.service.salesRep'])->find($this->proofId);
        if (! $proof) {
            return;
        }

        $rep = $this->resolveSalesRep($proof);
        if (! $rep) {
            return; // sin ejecutivo asociado: nadie a quién avisar
        }

        $clientName = $proof->client?->user?->name ?? 'un cliente';
        $folio = $proof->paymentOrder?->folio ?? ('comprobante #'.$proof->id);
        $subject = 'Nuevo comprobante de pago de '.$clientName;
        $body = implode("\n", [
            'Hola '.$rep->name.',',
            '',
            $clientName.' subió un comprobante de pago ('.$folio.') por '.$proof->amount()->format().'.',
            'Revísalo y concílialo en el panel de administración.',
        ]);

        if ($rep->email) {
            $mail->send($rep->email, $subject, $body, [
                'kind' => 'payment_proof',
                'audience' => 'sales_rep',
                'cta' => ['label' => 'Revisar comprobante', 'url' => route('admin.payment-orders.index')],
            ]);
        }

        if ($rep->phone) {
            $whatsapp->sendMessage($rep->phone, 'payment_proof_received', [
                'client' => $clientName,
                'folio' => $folio,
                'amount' => $proof->amount()->format(),
            ]);
        }
    }

    private function resolveSalesRep(PaymentProof $proof): ?SalesRep
    {
        // Preferente: el ejecutivo del servicio de la orden del comprobante.
        $rep = $proof->paymentOrder?->service?->salesRep;
        if ($rep) {
            return $rep;
        }

        // Si no hay servicio directo pero el cliente tiene un único ejecutivo
        // (derivado de sus servicios), se usa ese.
        $reps = $proof->client?->salesReps() ?? collect();

        return $reps->count() === 1 ? $reps->first() : null;
    }
}
