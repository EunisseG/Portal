<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Services\PaymentOrderService;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Jobs\ProcessInboundWebhook;
use App\Integrations\Providers\MercadoPago\FakeMercadoPagoProvider;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function pendingInvoice(int $totalCents = 100000): Invoice
    {
        $client = Client::factory()->create();

        return Invoice::factory()->create([
            'client_id' => $client->id,
            'total_cents' => $totalCents,
            'amount_paid_cents' => 0,
            'status' => InvoiceStatus::Pending->value,
        ]);
    }

    public function test_create_generates_order_with_folio_and_checkout(): void
    {
        $invoice = $this->pendingInvoice();

        $order = app(PaymentOrderService::class)->create($invoice, new FakeMercadoPagoProvider());

        $this->assertStringStartsWith('PO-', $order->folio);
        $this->assertSame(PaymentOrderStatus::Pending, $order->status);
        $this->assertSame($invoice->id, $order->invoice_id);
        $this->assertSame(100000, $order->amount_cents);
        $this->assertNotEmpty($order->checkout_url);
        // Caduca en 5 días.
        $this->assertSame(now()->addDays(5)->toDateString(), $order->expires_at->toDateString());
    }

    public function test_creating_new_order_cancels_previous_pending_one(): void
    {
        $invoice = $this->pendingInvoice();
        $service = app(PaymentOrderService::class);

        $first = $service->create($invoice, new FakeMercadoPagoProvider());
        $second = $service->create($invoice, new FakeMercadoPagoProvider());

        $this->assertSame(PaymentOrderStatus::Cancelled, $first->fresh()->status);
        $this->assertSame(PaymentOrderStatus::Pending, $second->fresh()->status);
    }

    public function test_mark_paid_registers_payment_and_marks_invoice_paid(): void
    {
        $invoice = $this->pendingInvoice(100000);
        $service = app(PaymentOrderService::class);
        $order = $service->create($invoice, new FakeMercadoPagoProvider());

        $service->markPaid($order, 'mp-pay-1');

        $this->assertSame(PaymentOrderStatus::Paid, $order->fresh()->status);
        $invoice->refresh();
        $this->assertSame(100000, $invoice->amount_paid_cents);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_mark_paid_is_idempotent(): void
    {
        $invoice = $this->pendingInvoice(100000);
        $service = app(PaymentOrderService::class);
        $order = $service->create($invoice, new FakeMercadoPagoProvider());

        $service->markPaid($order);
        $service->markPaid($order); // segunda vez: no debe duplicar el pago

        $this->assertSame(1, $invoice->fresh()->payments()->count());
    }

    public function test_expire_marks_pending_order_expired(): void
    {
        $invoice = $this->pendingInvoice();
        $order = app(PaymentOrderService::class)->create($invoice, new FakeMercadoPagoProvider());

        // Simula que ya pasó su plazo.
        $order->update(['expires_at' => now()->subDay()]);

        $this->artisan('payment-orders:expire')->assertExitCode(0);

        $this->assertSame(PaymentOrderStatus::Expired, $order->fresh()->status);
    }

    public function test_expire_does_not_touch_paid_order(): void
    {
        $invoice = $this->pendingInvoice();
        $service = app(PaymentOrderService::class);
        $order = $service->create($invoice, new FakeMercadoPagoProvider());
        $service->markPaid($order);
        $order->update(['expires_at' => now()->subDay()]);

        $this->artisan('payment-orders:expire')->assertExitCode(0);

        $this->assertSame(PaymentOrderStatus::Paid, $order->fresh()->status);
    }

    public function test_webhook_process_conciliates_order_as_paid(): void
    {
        // Fuerza el fake como gateway de pagos.
        $this->app->bind(PaymentGateway::class, fn () => new FakeMercadoPagoProvider());

        $invoice = $this->pendingInvoice(100000);
        $order = app(PaymentOrderService::class)->create($invoice, new FakeMercadoPagoProvider());

        // Simula el procesamiento del webhook (lo que haría ProcessInboundWebhook).
        $handler = app(\App\Integrations\Webhooks\Handlers\MercadoPagoWebhookHandler::class);
        $handler->process([
            'type' => 'payment',
            'external_reference' => $order->external_reference,
            'status' => 'approved',
            'data' => ['id' => 'mp-999'],
        ]);

        $this->assertSame(PaymentOrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_webhook_ignores_non_approved_status(): void
    {
        $invoice = $this->pendingInvoice(100000);
        $order = app(PaymentOrderService::class)->create($invoice, new FakeMercadoPagoProvider());

        $handler = app(\App\Integrations\Webhooks\Handlers\MercadoPagoWebhookHandler::class);
        $handler->process([
            'type' => 'payment',
            'external_reference' => $order->external_reference,
            'status' => 'pending',
            'data' => ['id' => 'mp-998'],
        ]);

        $this->assertSame(PaymentOrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(InvoiceStatus::Pending, $invoice->fresh()->status);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    private function makeOrder(PaymentOrderStatus $status, string $folioSuffix): PaymentOrder
    {
        $client = Client::factory()->create();

        return PaymentOrder::create([
            'folio' => 'PO-TEST-'.$folioSuffix,
            'client_id' => $client->id,
            'invoice_id' => null,
            'amount_cents' => 100000,
            'currency' => 'MXN',
            'status' => $status->value,
            'gateway' => 'mercadopago',
            'expires_at' => now()->addDays(5),
        ]);
    }

    public function test_payment_orders_status_kpis_and_chart_have_distribution_data(): void
    {
        $this->makeOrder(PaymentOrderStatus::Pending, '1');
        $this->makeOrder(PaymentOrderStatus::Pending, '2');
        $this->makeOrder(PaymentOrderStatus::Paid, '3');
        $this->makeOrder(PaymentOrderStatus::Expired, '4');
        $this->makeOrder(PaymentOrderStatus::Cancelled, '5');

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Billing\PaymentOrderIndex::class)
            ->assertSee('Distribución de órdenes de pago por estado')
            ->assertViewHas('kpis', fn ($kpis) => $kpis['pending'] === 2
                && $kpis['paid'] === 1
                && $kpis['expired'] === 1
                && $kpis['cancelled'] === 1
                && $kpis['total'] === 5)
            // chart['data'] = [pendiente, pagada, expirada, cancelada]
            ->assertViewHas('chart', fn ($chart) => $chart['data'] === [2, 1, 1, 1]
                && count($chart['labels']) === 4);
    }
}
