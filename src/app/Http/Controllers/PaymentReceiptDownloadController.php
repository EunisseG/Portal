<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Services\PaymentReceiptData;
use App\Support\Branding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Genera y descarga el recibo de una orden de pago en PDF (estilo recibo). Usa
 * los mismos datos que la pantalla web (PaymentReceiptData). Autoriza a un
 * administrador con permiso de facturas o al cliente dueño de la orden.
 */
class PaymentReceiptDownloadController extends Controller
{
    public function __invoke(PaymentOrder $paymentOrder, PaymentReceiptData $receiptData, Branding $branding): Response
    {
        $this->authorizeReceipt($paymentOrder);

        $data = $receiptData->build($paymentOrder);

        // dompdf no puede resolver URLs públicas: le pasamos la ruta local del
        // archivo del logo si existe (si no, la vista cae al nombre en texto).
        $data['logoSrc'] = $this->localLogoPath($branding);

        $pdf = Pdf::loadView('pdf.payment-receipt', $data)->setPaper('letter');

        return $pdf->download('recibo-'.$data['receiptNumber'].'.pdf');
    }

    /** Admin con permiso de facturas, o el cliente dueño de la orden. */
    private function authorizeReceipt(PaymentOrder $order): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        if ($user->can('invoices.view')) {
            return;
        }

        $account = $user->clientAccount();
        $isOwner = $account && (int) $account->id === (int) $order->client_id
            && (! $user->isSubUser() || $user->can('client.invoices.view'));

        abort_unless($isOwner, 403);
    }

    /** Ruta absoluta en disco del logo (o null si no hay o no existe). */
    private function localLogoPath(Branding $branding): ?string
    {
        $path = $branding->logoPath();

        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($path) ? $disk->path($path) : null;
    }
}
