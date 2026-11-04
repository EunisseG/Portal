<?php

namespace Database\Seeders;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\InvoiceService;
use App\Domain\Clients\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Facturas de ejemplo (desarrollo). Genera un PDF mínimo real por factura en el
 * disco privado, para que la descarga funcione en las pruebas manuales.
 */
class InvoicesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk(InvoiceService::DISK);

        // Cliente demo principal + algunos aleatorios.
        $clients = collect();
        $demo = Client::whereHas('user', fn ($q) => $q->where('email', 'cliente@wortev.test'))->first();
        if ($demo) {
            $clients->push($demo);
        }
        $clients = $clients->merge(Client::query()->inRandomOrder()->limit(4)->get())->unique('id');

        foreach ($clients as $client) {
            foreach ([InvoiceStatus::Pending, InvoiceStatus::Paid] as $i => $status) {
                $path = 'invoices/'.$client->id.'/'.Str::uuid().'.pdf';
                $folio = 'WV-2026-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT);
                $disk->put($path, $this->minimalPdf($folio));

                Invoice::factory()->create([
                    'client_id' => $client->id,
                    'folio' => $folio,
                    'status' => $status->value,
                    'pdf_path' => $path,
                    'issued_at' => now()->subDays(10 * ($i + 1))->toDateString(),
                    'due_at' => now()->addDays(20 - 10 * $i)->toDateString(),
                ]);
            }
        }
    }

    /**
     * Devuelve el contenido de un PDF mínimo válido con el folio incrustado.
     */
    private function minimalPdf(string $folio): string
    {
        $text = 'Factura demo '.$folio;

        return "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            ."4 0 obj<</Length 60>>stream\nBT /F1 18 Tf 72 720 Td ({$text}) Tj ET\nendstream endobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF";
    }
}
