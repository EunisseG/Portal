<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\InvoiceService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceDownloadController extends Controller
{
    /**
     * Descarga el PDF de la factura. Solo el admin con permiso o el cliente dueño.
     */
    public function pdf(Invoice $invoice): StreamedResponse
    {
        $this->authorize('download', $invoice);

        return $this->stream($invoice->pdf_path, 'factura-'.($invoice->folio ?: $invoice->id).'.pdf');
    }

    /**
     * Descarga el XML del CFDI (si existe).
     */
    public function xml(Invoice $invoice): StreamedResponse
    {
        $this->authorize('download', $invoice);

        abort_unless($invoice->hasXml(), 404);

        return $this->stream($invoice->xml_path, 'factura-'.($invoice->folio ?: $invoice->id).'.xml');
    }

    private function stream(string $path, string $downloadName): StreamedResponse
    {
        $disk = Storage::disk(InvoiceService::DISK);

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $downloadName);
    }
}
