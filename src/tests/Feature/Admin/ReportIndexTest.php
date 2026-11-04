<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Clients\Models\Client;
use App\Livewire\Admin\Reports\ReportIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ReportIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_reports_page_renders_with_all_charts(): void
    {
        Invoice::factory()->create(['status' => InvoiceStatus::Paid->value]);
        Invoice::factory()->create(['status' => InvoiceStatus::Pending->value]);

        Livewire::actingAs($this->admin())
            ->test(ReportIndex::class)
            ->assertOk()
            ->assertSee('Gráficas')
            ->assertSee('Ingresos por facturas pagadas')
            ->assertSee('Facturas por estado')
            ->assertSee('Tickets de clientes por estado')
            ->assertSee('Tickets internos por estado')
            ->assertSee('Productos por estado')
            ->assertViewHas('invoiceChart', fn ($c) => $c['total'] === 2 && count($c['labels']) === 4)
            ->assertViewHas('clientChart', fn ($c) => count($c['labels']) === 3);
    }

    public function test_revenue_chart_sums_payments_by_month(): void
    {
        $invoice = Invoice::factory()->create([
            'total_cents' => 500000,
            'status' => InvoiceStatus::Paid->value,
        ]);

        // Dos pagos el mes actual (90000 + 10000) y uno el mes pasado (50000).
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 90000,
            'currency' => 'MXN',
            'method' => 'transfer',
            'paid_at' => Carbon::now()->startOfMonth()->toDateString(),
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 10000,
            'currency' => 'MXN',
            'method' => 'transfer',
            'paid_at' => Carbon::now()->startOfMonth()->addDays(2)->toDateString(),
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 50000,
            'currency' => 'MXN',
            'method' => 'transfer',
            'paid_at' => Carbon::now()->startOfMonth()->subMonth()->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ReportIndex::class)
            ->assertViewHas('revenue', function ($revenue) {
                // Ventana de 12 meses: mes actual (último) = 1000.00, anterior = 500.00.
                return count($revenue['labels']) === 12
                    && count($revenue['data']) === 12
                    && $revenue['totalCents'] === 150000
                    && $revenue['data'][11] === 1000.0
                    && $revenue['data'][10] === 500.0;
            });
    }

    public function test_support_role_cannot_view_reports(): void
    {
        // support no tiene invoices.view, así que la pantalla le queda vedada.
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        Livewire::actingAs($support)
            ->test(ReportIndex::class)
            ->assertForbidden();
    }

    public function test_billing_role_can_view_reports(): void
    {
        // billing sí tiene invoices.view.
        $billing = User::factory()->create();
        $billing->assignRole(RoleName::Billing->value);

        Livewire::actingAs($billing)
            ->test(ReportIndex::class)
            ->assertOk();
    }

    public function test_date_range_filters_status_distributions(): void
    {
        // Una factura antigua (enero) y una reciente (junio).
        $old = Invoice::factory()->create(['status' => InvoiceStatus::Paid->value]);
        $old->forceFill(['created_at' => '2026-01-10'])->save();

        $recent = Invoice::factory()->create(['status' => InvoiceStatus::Paid->value]);
        $recent->forceFill(['created_at' => '2026-06-10'])->save();

        Livewire::actingAs($this->admin())
            ->test(ReportIndex::class)
            ->set('from', '2026-06-01')
            ->set('to', '2026-06-30')
            ->call('applyFilter')
            // Solo la factura de junio cae en el rango.
            ->assertViewHas('invoiceChart', fn ($c) => $c['total'] === 1);
    }

    public function test_date_range_filters_revenue_by_payment_date(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Paid->value]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 30000,
            'currency' => 'MXN',
            'method' => 'transfer',
            'paid_at' => '2026-03-15',
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 70000,
            'currency' => 'MXN',
            'method' => 'transfer',
            'paid_at' => '2026-07-15',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ReportIndex::class)
            ->set('from', '2026-03-01')
            ->set('to', '2026-05-31')
            ->call('applyFilter')
            // Solo el pago de marzo (300.00) entra; 3 meses mar-abr-may.
            ->assertViewHas('revenue', fn ($r) => $r['totalCents'] === 30000
                && count($r['labels']) === 3);
    }

    public function test_reset_filter_restores_last_12_months(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ReportIndex::class)
            ->set('from', '2026-06-01')
            ->set('to', '2026-06-30')
            ->call('resetFilter')
            ->assertViewHas('revenue', fn ($r) => count($r['labels']) === 12);
    }
}
