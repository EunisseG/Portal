<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Billing\Models\Invoice;
use App\Integrations\Contracts\MailGateway;
use App\Integrations\Contracts\WhatsAppGateway;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa al CLIENTE que ya tiene una factura disponible en el portal, por correo
 * y por WhatsApp (si tiene número). Se dispara al subir la factura.
 */
class SendInvoiceAvailableNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $invoiceId)
    {
        $this->onQueue('integrations');
    }

    public function handle(MailGateway $mail, WhatsAppGateway $whatsapp): void
    {
        $invoice = Invoice::with('client.user')->find($this->invoiceId);
        if (! $invoice) {
            return;
        }

        $client = $invoice->client;
        $user = $client?->user;
        $folio = $invoice->folio ?: '#'.$invoice->id;
        $brand = app(Branding::class)->name();
        $url = route('client.invoices.index');

        if ($user?->email) {
            $subject = 'Tu factura '.$folio.' ya está disponible';
            $body = implode("\n", [
                'Hola '.($user->name ?? '').',',
                '',
                'Tu factura '.$folio.' por '.$invoice->total()->format().' ya está disponible en el portal.',
                'Puedes consultarla y descargarla desde tu cuenta.',
                '',
                'Gracias por tu preferencia.',
                $brand,
            ]);
            $mail->send($user->email, $subject, $body, [
                'kind' => 'invoice',
                'audience' => 'client',
                'cta' => ['label' => 'Ver mi factura', 'url' => $url],
            ]);
        }

        if ($client?->whatsapp) {
            // Plantilla aprobada en Meta; variables en orden: folio, monto, marca.
            $whatsapp->sendMessage($client->whatsapp, 'invoice_available', [
                'folio' => $folio,
                'amount' => $invoice->total()->format(),
                'brand' => $brand,
            ]);
        }
    }
}
