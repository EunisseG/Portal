<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\InvoiceService;
use App\Domain\Clients\Models\Client;
use App\Livewire\Admin\Invoices\InvoiceForm;
use App\Livewire\Admin\Invoices\InvoiceIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake(InvoiceService::DISK);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_admin_can_upload_an_invoice_with_pdf(): void
    {
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(InvoiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('folio', 'WV-2026-00123')
            ->set('total', '1160.00')
            ->set('status', InvoiceStatus::Pending->value)
            ->set('pdf', UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertRedirect(route('admin.invoices.index'));

        $invoice = Invoice::where('folio', 'WV-2026-00123')->firstOrFail();
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame(116000, $invoice->total_cents);
        $this->assertNotEmpty($invoice->pdf_path);
        Storage::disk(InvoiceService::DISK)->assertExists($invoice->pdf_path);

        $this->assertDatabaseHas('audits', ['action' => AuditAction::InvoiceCreated->value]);
    }

    public function test_pdf_is_required_when_uploading(): void
    {
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(InvoiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('total', '100')
            ->call('save')
            ->assertHasErrors('pdf');
    }

    public function test_admin_can_mark_invoice_as_paid(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Pending->value]);

        Livewire::actingAs($this->admin())
            ->test(InvoiceIndex::class)
            ->call('changeStatus', $invoice->id, InvoiceStatus::Paid->value);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertDatabaseHas('audits', ['action' => AuditAction::InvoiceStatusChanged->value]);
    }

    public function test_support_role_cannot_manage_invoices(): void
    {
        // support no tiene invoices.view ni manage.
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        Livewire::actingAs($support)
            ->test(InvoiceForm::class)
            ->assertForbidden();
    }

    public function test_extractor_reads_folio_and_total_from_cfdi_xml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Folio="A-987" Total="2320.00" Fecha="2026-03-15T10:00:00">
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" UUID="12345678-90AB-CDEF-1234-567890ABCDEF"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;

        $data = app(\App\Domain\Billing\Services\InvoiceExtractor::class)->fromXml($xml);

        $this->assertSame('A-987', $data->folio);
        $this->assertSame('12345678-90AB-CDEF-1234-567890ABCDEF', $data->uuid);
        $this->assertSame(2320.0, $data->total);
        $this->assertSame('2026-03-15', $data->issuedAt);
        $this->assertSame('xml', $data->source);
    }

    public function test_invoices_status_kpis_and_chart_have_distribution_data(): void
    {
        Invoice::factory()->create(['status' => InvoiceStatus::Pending->value]);
        Invoice::factory()->create(['status' => InvoiceStatus::Pending->value]);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid->value]);
        Invoice::factory()->create(['status' => InvoiceStatus::Overdue->value]);
        Invoice::factory()->create(['status' => InvoiceStatus::Cancelled->value]);

        Livewire::actingAs($this->admin())
            ->test(InvoiceIndex::class)
            ->assertSee('Distribución de facturas por estado')
            ->assertViewHas('kpis', fn ($kpis) => $kpis['pending'] === 2
                && $kpis['paid'] === 1
                && $kpis['overdue'] === 1
                && $kpis['cancelled'] === 1
                && $kpis['total'] === 5)
            // chart['data'] = [pendiente, pagada, vencida, cancelada]
            ->assertViewHas('chart', fn ($chart) => $chart['data'] === [2, 1, 1, 1]
                && count($chart['labels']) === 4);
    }
}
