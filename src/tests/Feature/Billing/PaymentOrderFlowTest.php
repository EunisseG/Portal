<?php

namespace Tests\Feature\Billing;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Enums\PaymentProofStatus;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Models\PaymentProof;
use App\Domain\Billing\Services\PaymentOrderService;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Models\Product;
use App\Domain\Services\Models\Service;
use App\Integrations\Providers\MercadoPago\FakeMercadoPagoProvider;
use App\Livewire\Admin\Billing\PaymentOrderIndex;
use App\Livewire\Admin\Clients\ClientForm;
use App\Livewire\Admin\Services\ServiceForm;
use App\Livewire\Client\Invoices\PaymentOrderReceipt;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Flujo de la orden de pago: bloqueo de pedidos pagados, marcado manual,
 * comprobantes subidos por el cliente y generación automática de renovaciones.
 */
class PaymentOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    private function pedido(Client $client, int $total = 116000, ?string $expiresAt = null): Service
    {
        $subtotal = (int) round($total / 1.16);

        return Service::factory()->create([
            'client_id' => $client->id,
            'unit_price_cents' => $subtotal,
            'subtotal_cents' => $subtotal,
            'discount_cents' => 0,
            'tax_cents' => $total - $subtotal,
            'tax_rate' => 16,
            'total_cents' => $total,
            'currency' => 'MXN',
            'expires_at' => $expiresAt ?? now()->addMonths(10)->toDateString(),
        ]);
    }

    private function orderFor(Service $service): PaymentOrder
    {
        return app(PaymentOrderService::class)->createForService($service, new FakeMercadoPagoProvider());
    }

    // ------------------------------------------------------------------
    // Punto 2: pedido pagado no se edita ni regenera orden
    // ------------------------------------------------------------------

    public function test_paid_service_cannot_be_edited_and_does_not_regenerate_order(): void
    {
        $service = $this->pedido(Client::factory()->create());
        $order = $this->orderFor($service);
        app(PaymentOrderService::class)->markPaid($order);

        $this->assertTrue($service->fresh()->isLockedByPayment());

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class, ['service' => $service->fresh()])
            ->assertRedirect(route('admin.services.show', $service));

        $this->assertSame(1, PaymentOrder::where('service_id', $service->id)->count());
    }

    public function test_service_with_pending_order_is_editable(): void
    {
        $service = $this->pedido(Client::factory()->create());
        $this->orderFor($service);

        $this->assertFalse($service->fresh()->isLockedByPayment());

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class, ['service' => $service->fresh()])
            ->assertNoRedirect();
    }

    public function test_service_unlocks_when_renewal_order_is_generated(): void
    {
        $service = $this->pedido(Client::factory()->create());
        $order = $this->orderFor($service);
        app(PaymentOrderService::class)->markPaid($order);
        $this->assertTrue($service->fresh()->isLockedByPayment());

        // Llega la renovación: nueva orden pendiente, el pedido se desbloquea.
        $this->orderFor($service->fresh());

        $this->assertFalse($service->fresh()->isLockedByPayment());
    }

    // ------------------------------------------------------------------
    // Punto 3: marcar pagada manualmente (incluye pedidos sin costo)
    // ------------------------------------------------------------------

    public function test_admin_can_mark_zero_cost_order_as_paid_and_invoice_is_generated(): void
    {
        $service = $this->pedido(Client::factory()->create(), 0);
        $order = $this->orderFor($service);

        Livewire::actingAs($this->admin())
            ->test(PaymentOrderIndex::class)
            ->call('startMark', $order->id)
            ->call('confirmMark')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(PaymentOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->invoice_id);
        $this->assertSame(InvoiceStatus::Paid, $order->invoice->status);
        // Sin costo no se registra ningún pago.
        $this->assertSame(0, $order->invoice->payments()->count());
    }

    public function test_admin_marking_order_with_amount_registers_payment_with_chosen_method(): void
    {
        $service = $this->pedido(Client::factory()->create(), 116000);
        $order = $this->orderFor($service);
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(PaymentOrderIndex::class)
            ->call('startMark', $order->id)
            ->set('markMethod', 'transfer')
            ->set('markReference', 'SPEI-123')
            ->call('confirmMark')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(PaymentOrderStatus::Paid, $order->status);
        $payment = $order->invoice->payments()->firstOrFail();
        $this->assertSame(116000, $payment->amount_cents);
        $this->assertSame('transfer', $payment->method->value);
        $this->assertSame($admin->id, $payment->recorded_by);
        $this->assertSame(InvoiceStatus::Paid, $order->invoice->status);
    }

    public function test_user_without_payments_permission_cannot_mark_order_paid(): void
    {
        $order = $this->orderFor($this->pedido(Client::factory()->create()));

        // Staff puede ver facturas pero no registrar pagos.
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(PaymentOrderIndex::class)
            ->call('startMark', $order->id)
            ->assertForbidden();

        $this->assertSame(PaymentOrderStatus::Pending, $order->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Punto 5: comprobante del cliente -> admin aprueba -> orden pagada
    // ------------------------------------------------------------------

    public function test_client_uploads_proof_and_admin_approval_marks_order_paid(): void
    {
        $me = $this->clientUser();
        $order = $this->orderFor($this->pedido($me->client, 116000));

        Livewire::actingAs($me)
            ->test(PaymentOrderReceipt::class, ['order' => $order])
            ->set('proofFile', UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'))
            ->set('proofMethod', 'transfer')
            ->set('proofReference', 'REF-9')
            ->call('submitProof')
            ->assertHasNoErrors();

        $proof = PaymentProof::where('payment_order_id', $order->id)->firstOrFail();
        $this->assertSame(PaymentProofStatus::Pending, $proof->status);
        $this->assertSame(116000, $proof->amount_cents);
        // Aún no está pagada: espera revisión.
        $this->assertSame(PaymentOrderStatus::Pending, $order->fresh()->status);

        Livewire::actingAs($this->admin())
            ->test(PaymentOrderIndex::class)
            ->call('approveProof', $proof->id);

        $order->refresh();
        $this->assertSame(PaymentProofStatus::Approved, $proof->fresh()->status);
        $this->assertSame(PaymentOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->invoice_id);
        $payment = $order->invoice->payments()->firstOrFail();
        $this->assertSame($proof->id, $payment->payment_proof_id);
        $this->assertSame(InvoiceStatus::Paid, $order->invoice->status);
    }

    public function test_client_cannot_send_second_proof_while_one_is_pending(): void
    {
        $me = $this->clientUser();
        $order = $this->orderFor($this->pedido($me->client));

        $component = Livewire::actingAs($me)
            ->test(PaymentOrderReceipt::class, ['order' => $order])
            ->set('proofFile', UploadedFile::fake()->create('uno.pdf', 50, 'application/pdf'))
            ->call('submitProof');

        $component
            ->set('proofFile', UploadedFile::fake()->create('dos.pdf', 50, 'application/pdf'))
            ->call('submitProof');

        $this->assertSame(1, PaymentProof::where('payment_order_id', $order->id)->count());
    }

    public function test_client_cannot_upload_proof_for_another_clients_order(): void
    {
        $me = $this->clientUser();
        $otherOrder = $this->orderFor($this->pedido(Client::factory()->create()));

        Livewire::actingAs($me)
            ->test(PaymentOrderReceipt::class, ['order' => $otherOrder])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Punto 1: generación de órdenes de renovación
    // ------------------------------------------------------------------

    public function test_renewal_command_generates_order_for_expiring_service_once(): void
    {
        $service = $this->pedido(Client::factory()->create(), 116000, now()->addDays(10)->toDateString());

        $this->artisan('payment-orders:generate-renewals')->assertExitCode(0);
        $this->assertSame(1, PaymentOrder::where('service_id', $service->id)->count());

        // Segunda corrida: ya hay una pendiente, no duplica.
        $this->artisan('payment-orders:generate-renewals')->assertExitCode(0);
        $this->assertSame(1, PaymentOrder::where('service_id', $service->id)->count());
    }

    public function test_renewal_command_ignores_services_far_from_expiring(): void
    {
        $service = $this->pedido(Client::factory()->create(), 116000, now()->addMonths(6)->toDateString());

        $this->artisan('payment-orders:generate-renewals')->assertExitCode(0);

        $this->assertSame(0, PaymentOrder::where('service_id', $service->id)->count());
    }

    public function test_renewal_command_skips_service_already_paid_this_cycle(): void
    {
        $service = $this->pedido(Client::factory()->create(), 116000, now()->addDays(10)->toDateString());
        $order = $this->orderFor($service);
        app(PaymentOrderService::class)->markPaid($order);

        $this->artisan('payment-orders:generate-renewals')->assertExitCode(0);

        $this->assertSame(1, PaymentOrder::where('service_id', $service->id)->count());
    }

    public function test_renewal_command_is_registered_in_the_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('payment-orders:generate-renewals')
            ->assertExitCode(0);
    }

    public function test_admin_menu_has_payment_proofs_section_for_orders(): void
    {
        $order = $this->orderFor($this->pedido(Client::factory()->create()));

        // La página de órdenes de pago muestra las órdenes y los comprobantes.
        $this->actingAs($this->admin())
            ->get(route('admin.payment-orders.index'))
            ->assertOk()
            ->assertSee('Órdenes de pago')
            ->assertSee($order->folio)
            ->assertSee('Comprobantes recibidos');
    }

    public function test_billing_role_sees_payment_orders_but_not_proof_review(): void
    {
        $order = $this->orderFor($this->pedido(Client::factory()->create()));

        // Billing ve las órdenes, pero no la lista de comprobantes recibidos
        // (no tiene payment_proofs.manage).
        $billing = User::factory()->create();
        $billing->assignRole(RoleName::Billing->value);

        $this->actingAs($billing)
            ->get(route('admin.payment-orders.index'))
            ->assertOk()
            ->assertSee($order->folio)
            ->assertDontSee('Comprobantes recibidos');
    }

    // ------------------------------------------------------------------
    // Punto 4: todo pedido nuevo genera su orden
    // ------------------------------------------------------------------

    public function test_initial_service_created_with_client_generates_payment_order(): void
    {
        $product = Product::create([
            'name' => 'Respaldo diario',
            'price_cents' => 15000,
            'currency' => 'MXN',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ClientForm::class)
            ->set('name', 'Con Orden')
            ->set('email', 'conorden@wortev.test')
            ->set('addProduct', true)
            ->set('productId', $product->id)
            ->call('save');

        $client = User::where('email', 'conorden@wortev.test')->firstOrFail()->client;
        $order = PaymentOrder::where('client_id', $client->id)->firstOrFail();

        $this->assertSame(PaymentOrderStatus::Pending, $order->status);
        $this->assertSame(15000, $order->amount_cents);
        $this->assertNotNull($order->service_id);
    }
}
