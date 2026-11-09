<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Services\PaymentOrderService;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Models\Service;
use App\Integrations\Contracts\PaymentGateway;
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

    /**
     * Crea un pedido (Service) con desglose simple: total = subtotal + IVA.
     * total 116000 => subtotal 100000 + 16% IVA 16000.
     */
    private function pedido(int $totalCents = 116000, int $subtotalCents = 100000, int $taxCents = 16000): Service
    {
        $client = Client::factory()->create();

        return Service::factory()->create([
            'client_id' => $client->id,
            'unit_price_cents' => $subtotalCents,
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => 0,
            'tax_cents' => $taxCents,
            'tax_rate' => 16,
            'total_cents' => $totalCents,
            'currency' => 'MXN',
        ]);
    }

    public function test_create_for_service_generates_order_with_folio_and_breakdown(): void
    {
        // El enlace de pago solo se genera con los pagos en modo automático.
        \App\Domain\Notifications\Models\Setting::put(
            \App\Support\OperationMode::KEY_PAYMENTS,
            \App\Support\OperationMode::MODE_AUTOMATIC,
        );

        $service = $this->pedido();

        $order = app(PaymentOrderService::class)->createForService($service, new FakeMercadoPagoProvider());

        $this->assertStringStartsWith('PO-', $order->folio);
        $this->assertSame(PaymentOrderStatus::Pending, $order->status);
        $this->assertSame($service->id, $order->service_id);
        $this->assertNull($order->invoice_id);
        $this->assertSame(116000, $order->amount_cents);
        $this->assertSame(100000, $order->subtotal_cents);
        $this->assertSame(16000, $order->tax_cents);
        $this->assertNotEmpty($order->checkout_url);
        // La línea base del pedido se copió a la orden.
        $this->assertSame(1, $order->items()->count());
        // Caduca en 5 días.
        $this->assertSame(now()->addDays(5)->toDateString(), $order->expires_at->toDateString());
    }

    public function test_free_service_creates_order_without_checkout(): void
    {
        // Pedido gratuito (promoción al 100%): total 0, sin cobro por pasarela.
        $service = $this->pedido(0, 0, 0);

        $order = app(PaymentOrderService::class)->createForService($service, new FakeMercadoPagoProvider());

        $this->assertSame(PaymentOrderStatus::Pending, $order->status);
        $this->assertSame(0, $order->amount_cents);
        $this->assertNull($order->checkout_url);
    }

    public function test_creating_new_order_cancels_previous_pending_one(): void
    {
        $service = $this->pedido();
        $svc = app(PaymentOrderService::class);

        $first = $svc->createForService($service, new FakeMercadoPagoProvider());
        $second = $svc->createForService($service, new FakeMercadoPagoProvider());

        $this->assertSame(PaymentOrderStatus::Cancelled, $first->fresh()->status);
        $this->assertSame(PaymentOrderStatus::Pending, $second->fresh()->status);
    }

    public function test_mark_paid_generates_invoice_from_service_and_registers_payment(): void
    {
        $service = $this->pedido(116000);
        $svc = app(PaymentOrderService::class);
        $order = $svc->createForService($service, new FakeMercadoPagoProvider());

        $svc->markPaid($order, 'mp-pay-1');

        $order->refresh();
        $this->assertSame(PaymentOrderStatus::Paid, $order->status);
        // Tras el pago se generó la factura desde el pedido.
        $this->assertNotNull($order->invoice_id);
        $invoice = $order->invoice;
        $this->assertSame(116000, $invoice->total_cents);
        $this->assertSame(116000, $invoice->amount_paid_cents);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_mark_paid_is_idempotent(): void
    {
        $service = $this->pedido(116000);
        $svc = app(PaymentOrderService::class);
        $order = $svc->createForService($service, new FakeMercadoPagoProvider());

        $svc->markPaid($order);
        $svc->markPaid($order); // segunda vez: no debe duplicar factura ni pago

        $order->refresh();
        $this->assertSame(1, $order->invoice->payments()->count());
        $this->assertSame(1, $service->invoices()->count());
    }

    public function test_expire_marks_pending_order_expired(): void
    {
        $service = $this->pedido();
        $order = app(PaymentOrderService::class)->createForService($service, new FakeMercadoPagoProvider());

        // Simula que ya pasó su plazo.
        $order->update(['expires_at' => now()->subDay()]);

        $this->artisan('payment-orders:expire')->assertExitCode(0);

        $this->assertSame(PaymentOrderStatus::Expired, $order->fresh()->status);
    }

    public function test_expire_does_not_touch_paid_order(): void
    {
        $service = $this->pedido();
        $svc = app(PaymentOrderService::class);
        $order = $svc->createForService($service, new FakeMercadoPagoProvider());
        $svc->markPaid($order);
        $order->update(['expires_at' => now()->subDay()]);

        $this->artisan('payment-orders:expire')->assertExitCode(0);

        $this->assertSame(PaymentOrderStatus::Paid, $order->fresh()->status);
    }

    public function test_webhook_process_conciliates_order_as_paid_and_generates_invoice(): void
    {
        // Fuerza el fake como gateway de pagos.
        $this->app->bind(PaymentGateway::class, fn () => new FakeMercadoPagoProvider());

        $service = $this->pedido(116000);
        $order = app(PaymentOrderService::class)->createForService($service, new FakeMercadoPagoProvider());

        $handler = app(\App\Integrations\Webhooks\Handlers\MercadoPagoWebhookHandler::class);
        $handler->process([
            'type' => 'payment',
            'external_reference' => $order->external_reference,
            'status' => 'approved',
            'data' => ['id' => 'mp-999'],
        ]);

        $order->refresh();
        $this->assertSame(PaymentOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->invoice_id);
        $this->assertSame(InvoiceStatus::Paid, $order->invoice->status);
    }

    public function test_webhook_ignores_non_approved_status(): void
    {
        $service = $this->pedido(116000);
        $order = app(PaymentOrderService::class)->createForService($service, new FakeMercadoPagoProvider());

        $handler = app(\App\Integrations\Webhooks\Handlers\MercadoPagoWebhookHandler::class);
        $handler->process([
            'type' => 'payment',
            'external_reference' => $order->external_reference,
            'status' => 'pending',
            'data' => ['id' => 'mp-998'],
        ]);

        $order->refresh();
        $this->assertSame(PaymentOrderStatus::Pending, $order->status);
        $this->assertNull($order->invoice_id);
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
            'service_id' => null,
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
            ->assertViewHas('chart', fn ($chart) => $chart['data'] === [2, 1, 1, 1]
                && count($chart['labels']) === 4);
    }
}
