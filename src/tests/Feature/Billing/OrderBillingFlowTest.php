<?php

namespace Tests\Feature\Billing;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentProofStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\PaymentProof;
use App\Domain\Billing\Services\InvoiceService;
use App\Domain\Billing\Services\PaymentService;
use App\Domain\Billing\Services\PricingService;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\AddonCatalogItem;
use App\Domain\Services\Models\Promotion;
use App\Domain\Services\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderBillingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function baseService(): Service
    {
        return Service::factory()->create([
            'unit_price_cents' => 100000, // 1000.00
            'tax_rate' => 16,
            'discount_type' => null,
            'discount_value' => null,
        ]);
    }

    public function test_recalculate_sets_breakdown_with_addons(): void
    {
        $service = $this->baseService();
        Addon::factory()->create(['service_id' => $service->id, 'price_cents' => 20000, 'status' => AddonStatus::Active->value]);
        Addon::factory()->create(['service_id' => $service->id, 'price_cents' => 5000, 'status' => AddonStatus::Cancelled->value]);

        app(PricingService::class)->recalculate($service->load('addons'));
        $service->refresh();

        // base 1000 + addon activo 200 = 1200 subtotal; el cancelado (50) no cuenta.
        $this->assertSame(120000, $service->subtotal_cents);
        $this->assertSame(0, $service->discount_cents);
        $this->assertSame(19200, $service->tax_cents);
        $this->assertSame(139200, $service->total_cents);
        $this->assertSame(139200, $service->price_cents);
    }

    public function test_apply_valid_promotion_reduces_total(): void
    {
        $service = $this->baseService();
        $promotion = Promotion::create([
            'code' => 'DESC10',
            'name' => 'Diez por ciento',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $applied = app(PricingService::class)->applyPromotion($service->load('addons'), $promotion);
        $service->refresh();

        $this->assertTrue($applied);
        $this->assertSame($promotion->id, $service->promotion_id);
        $this->assertSame(10000, $service->discount_cents); // 10% de 1000
        $this->assertSame(104400, $service->total_cents);    // 900 + 16%
    }

    public function test_expired_promotion_is_rejected(): void
    {
        $service = $this->baseService();
        $promotion = Promotion::create([
            'code' => 'VIEJA',
            'name' => 'Vencida',
            'discount_type' => 'percent',
            'discount_value' => 50,
            'starts_at' => now()->subYear()->toDateString(),
            'ends_at' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        $applied = app(PricingService::class)->applyPromotion($service->load('addons'), $promotion);

        $this->assertFalse($applied);
        $this->assertNull($service->fresh()->promotion_id);
    }

    public function test_generate_invoice_from_service_creates_items(): void
    {
        $service = $this->baseService();
        Addon::factory()->create(['service_id' => $service->id, 'name' => 'Respaldo', 'price_cents' => 20000, 'status' => AddonStatus::Active->value]);

        $promotion = Promotion::create([
            'code' => 'P10', 'name' => 'Promo 10', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true,
        ]);
        app(PricingService::class)->applyPromotion($service->load('addons'), $promotion);
        $service->refresh()->load(['addons', 'promotion']);

        $invoice = app(InvoiceService::class)->createFromService($service);
        $invoice->refresh()->load('items');

        $this->assertSame($service->id, $invoice->service_id);
        $this->assertSame($service->total_cents, $invoice->total_cents);
        $this->assertSame(InvoiceStatus::Pending, $invoice->status);
        // 3 líneas: base + complemento + descuento (negativo)
        $this->assertCount(3, $invoice->items);
        $this->assertTrue($invoice->items->contains(fn ($i) => $i->amount_cents < 0));
    }

    public function test_partial_then_full_payment_marks_invoice_paid(): void
    {
        $invoice = Invoice::factory()->create(['total_cents' => 100000, 'amount_paid_cents' => 0, 'status' => InvoiceStatus::Pending->value]);
        $payments = app(PaymentService::class);

        $payments->record($invoice, ['amount_cents' => 40000, 'method' => 'transfer', 'paid_at' => now()->toDateString()]);
        $invoice->refresh();
        $this->assertSame(40000, $invoice->amount_paid_cents);
        $this->assertSame(60000, $invoice->balanceCents());
        $this->assertSame(InvoiceStatus::Pending, $invoice->status);

        $payments->record($invoice, ['amount_cents' => 60000, 'method' => 'transfer', 'paid_at' => now()->toDateString()]);
        $invoice->refresh();
        $this->assertSame(100000, $invoice->amount_paid_cents);
        $this->assertSame(0, $invoice->balanceCents());
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(2, $invoice->payments()->count());
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    /** Orden de pago con un comprobante pendiente para un cliente. */
    private function orderWithProof(Client $client): PaymentProof
    {
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'unit_price_cents' => 50000, 'subtotal_cents' => 50000,
            'discount_cents' => 0, 'tax_cents' => 8000, 'tax_rate' => 16,
            'total_cents' => 58000, 'currency' => 'MXN',
        ]);
        $order = app(\App\Domain\Billing\Services\PaymentOrderService::class)
            ->createForService($service, new \App\Integrations\Providers\MercadoPago\FakeMercadoPagoProvider());

        return PaymentProof::create([
            'client_id' => $client->id,
            'payment_order_id' => $order->id,
            'file_path' => 'payment-proofs/'.$client->id.'/p.pdf',
            'original_name' => 'p.pdf',
            'amount_cents' => 58000,
            'currency' => 'MXN',
            'method' => 'transfer',
            'paid_at' => now()->toDateString(),
            'status' => PaymentProofStatus::Pending->value,
        ]);
    }

    public function test_approving_order_proof_marks_order_paid_and_generates_invoice_for_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $proof = $this->orderWithProof(Client::factory()->create());

        \Livewire\Livewire::test(\App\Livewire\Admin\Billing\PaymentOrderIndex::class)
            ->call('approveProof', $proof->id);

        $proof->refresh();
        $order = $proof->paymentOrder;
        $this->assertSame(PaymentProofStatus::Approved, $proof->status);
        $this->assertSame(\App\Domain\Billing\Enums\PaymentOrderStatus::Paid, $order->status);
        // Se generó la factura del pedido y se registró el pago.
        $this->assertNotNull($order->invoice_id);
        $this->assertSame(InvoiceStatus::Paid, $order->invoice->status);
        $this->assertSame(1, Payment::where('invoice_id', $order->invoice_id)->count());
        $this->assertSame($proof->id, Payment::where('invoice_id', $order->invoice_id)->first()->payment_proof_id);
    }

    public function test_staff_approves_order_proof_but_cannot_mark_it_paid(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff);

        $proof = $this->orderWithProof(Client::factory()->create());

        \Livewire\Livewire::test(\App\Livewire\Admin\Billing\PaymentOrderIndex::class)
            ->call('approveProof', $proof->id);

        $proof->refresh();
        $this->assertSame(PaymentProofStatus::Approved, $proof->status);
        // Staff no tiene payments.record: se aprueba pero la orden sigue pendiente.
        $this->assertSame(\App\Domain\Billing\Enums\PaymentOrderStatus::Pending, $proof->paymentOrder->status);
    }

    public function test_add_addon_from_catalog_recalculates_service_total(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $service = $this->baseService();
        app(PricingService::class)->recalculate($service->load('addons'));

        $item = AddonCatalogItem::create([
            'name' => 'IP dedicada', 'price_cents' => 30000, 'currency' => 'MXN', 'is_active' => true,
        ]);

        \Livewire\Livewire::test(\App\Livewire\Admin\Services\ServiceShow::class, ['service' => $service])
            ->set('addonMode', 'catalog')
            ->set('addonCatalogId', (string) $item->id)
            ->set('addonStatus', 'active')
            ->call('addAddon')
            ->assertHasNoErrors();

        $service->refresh();
        $this->assertSame(1, $service->addons()->count());
        $this->assertSame($item->id, $service->addons()->first()->catalog_item_id);
        // base 1000 + 300 = 1300 subtotal; total 1508 con 16%
        $this->assertSame(130000, $service->subtotal_cents);
        $this->assertSame(150800, $service->total_cents);
    }

    public function test_add_recurring_catalog_addon_freezes_cycle_price(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $service = $this->baseService();
        app(PricingService::class)->recalculate($service->load('addons'));

        // Complemento recurrente con precio mensual de 200 (sin IVA) + IVA 16%.
        $item = AddonCatalogItem::create([
            'name' => 'Respaldo diario',
            'billing_mode' => \App\Domain\Services\Enums\BillingMode::Recurring->value,
            'price_cents' => 20000,
            'currency' => 'MXN',
            'is_active' => true,
        ]);
        $item->prices()->create([
            'billing_cycle' => \App\Domain\Services\Enums\BillingCycle::Monthly->value,
            'unit_price_cents' => 20000,
            'tax_rate' => 16,
            'currency' => 'MXN',
        ]);

        \Livewire\Livewire::test(\App\Livewire\Admin\Services\ServiceShow::class, ['service' => $service])
            ->set('addonMode', 'catalog')
            ->set('addonCatalogId', (string) $item->id)
            ->set('addonCycle', 'monthly')
            ->set('addonStatus', 'active')
            ->call('addAddon')
            ->assertHasNoErrors();

        $addon = $service->addons()->first();
        // Congela el precio unitario del ciclo (SIN IVA; el IVA lo aplica el pedido).
        $this->assertSame(20000, $addon->price_cents);
        $this->assertSame(\App\Domain\Services\Enums\BillingCycle::Monthly, $addon->billing_cycle);

        // base 1000 + 200 = 1200 subtotal; total 1392 con 16% (sin doble IVA).
        $service->refresh();
        $this->assertSame(120000, $service->subtotal_cents);
        $this->assertSame(139200, $service->total_cents);
    }

    public function test_recurring_catalog_addon_requires_a_cycle(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $service = $this->baseService();

        $item = AddonCatalogItem::create([
            'name' => 'Sin ciclo elegido',
            'billing_mode' => \App\Domain\Services\Enums\BillingMode::Recurring->value,
            'price_cents' => 20000,
            'currency' => 'MXN',
            'is_active' => true,
        ]);
        $item->prices()->create([
            'billing_cycle' => \App\Domain\Services\Enums\BillingCycle::Monthly->value,
            'unit_price_cents' => 20000,
            'tax_rate' => 16,
            'currency' => 'MXN',
        ]);

        \Livewire\Livewire::test(\App\Livewire\Admin\Services\ServiceShow::class, ['service' => $service])
            ->set('addonMode', 'catalog')
            ->set('addonCatalogId', (string) $item->id)
            ->set('addonCycle', '')
            ->set('addonStatus', 'active')
            ->call('addAddon')
            ->assertHasErrors('addonCycle');

        $this->assertSame(0, $service->addons()->count());
    }

    public function test_recurring_addon_gets_expiry_from_its_cycle_on_create(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $service = $this->baseService();

        $item = AddonCatalogItem::create([
            'name' => 'Mensual con vencimiento',
            'billing_mode' => \App\Domain\Services\Enums\BillingMode::Recurring->value,
            'price_cents' => 20000,
            'currency' => 'MXN',
            'is_active' => true,
        ]);
        $item->prices()->create([
            'billing_cycle' => \App\Domain\Services\Enums\BillingCycle::Monthly->value,
            'unit_price_cents' => 20000,
            'tax_rate' => 16,
            'currency' => 'MXN',
        ]);

        \Livewire\Livewire::test(\App\Livewire\Admin\Services\ServiceShow::class, ['service' => $service])
            ->set('addonMode', 'catalog')
            ->set('addonCatalogId', (string) $item->id)
            ->set('addonCycle', 'monthly')
            ->set('addonStatus', 'active')
            ->call('addAddon')
            ->assertHasNoErrors();

        $addon = $service->addons()->first();
        // Al crearse recurrente sin fecha manual, vence en un ciclo (1 mes).
        $this->assertNotNull($addon->expires_at);
        $this->assertSame(now()->addMonth()->toDateString(), $addon->expires_at->toDateString());
    }

    public function test_expire_command_suspends_overdue_recurring_addon_and_recalculates(): void
    {
        $service = $this->baseService();

        $addon = Addon::factory()->create([
            'service_id' => $service->id,
            'price_cents' => 20000,
            'status' => AddonStatus::Active->value,
            'billing_cycle' => \App\Domain\Services\Enums\BillingCycle::Monthly->value,
            'expires_at' => now()->subDay()->toDateString(), // ya venció
        ]);

        // Antes: el complemento activo cuenta en el total (1000 + 200 = 1200).
        app(PricingService::class)->recalculate($service->load('addons'));
        $service->refresh();
        $this->assertSame(120000, $service->subtotal_cents);

        $this->artisan('addons:expire')->assertExitCode(0);

        // El complemento queda suspendido y deja de cobrarse.
        $this->assertSame(AddonStatus::Suspended, $addon->fresh()->status);
        $service->refresh();
        $this->assertSame(100000, $service->subtotal_cents);
        $this->assertSame(116000, $service->total_cents);
    }

    public function test_renew_addon_advances_cycle_and_reactivates(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $service = $this->baseService();

        $addon = Addon::factory()->create([
            'service_id' => $service->id,
            'price_cents' => 20000,
            'status' => AddonStatus::Suspended->value,
            'billing_cycle' => \App\Domain\Services\Enums\BillingCycle::Monthly->value,
            'expires_at' => now()->subDays(5)->toDateString(),
        ]);

        \Livewire\Livewire::test(\App\Livewire\Admin\Services\ServiceShow::class, ['service' => $service])
            ->call('renewAddon', $addon->id);

        $fresh = $addon->fresh();
        // Reactivado y con vencimiento un ciclo adelante (desde hoy, pues venció).
        $this->assertSame(AddonStatus::Active, $fresh->status);
        $this->assertSame(now()->addMonth()->toDateString(), $fresh->expires_at->toDateString());

        // Vuelve a contar en el total del pedido.
        $service->refresh();
        $this->assertSame(120000, $service->subtotal_cents);
    }

    public function test_one_time_addon_is_not_expired_by_command(): void
    {
        $service = $this->baseService();

        $addon = Addon::factory()->create([
            'service_id' => $service->id,
            'price_cents' => 20000,
            'status' => AddonStatus::Active->value,
            'billing_cycle' => null, // pago único
            'expires_at' => now()->subMonth()->toDateString(),
        ]);

        $this->artisan('addons:expire')->assertExitCode(0);

        // Pago único no se suspende aunque tenga fecha pasada.
        $this->assertSame(AddonStatus::Active, $addon->fresh()->status);
    }
}
