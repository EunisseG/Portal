<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Models\PaymentProof;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofDownloadController extends Controller
{
    /**
     * Descarga el archivo del comprobante de pago. Protegido por policy:
     * un gestor con permiso o el cliente dueño del comprobante.
     */
    public function __invoke(PaymentProof $paymentProof): StreamedResponse
    {
        $this->authorize('download', $paymentProof);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($paymentProof->file_path), 404);

        $name = $paymentProof->original_name ?: ('comprobante-'.$paymentProof->id);

        return $disk->download($paymentProof->file_path, $name);
    }
}
