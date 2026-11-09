<?php

namespace Tests\Feature\Billing;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Models\PaymentProof;
use App\Domain\Billing\Services\InvoiceService;
use App\Domain\Billing\Services\PaymentOrderService;
use App\Domain\Clients\Models\Client;
use App\Domain\Notifications\Jobs\SendInvoiceAvailableNotification;
use App\Domain\Notifications\Jobs\SendPaymentProofNotification;
use App\Domain\Sales\Models\SalesRep;
use App\Domain\Services\Models\Service;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\MailGateway;
use App\Integrations\Contracts\WhatsAppGateway;
use App\Integrations\Providers\MercadoPago\FakeMercadoPagoProvider;
use App\Livewire\Admin\Billing\PaymentOrderIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Notificaciones al subir factura (al cliente) y al subir comprobante (al
 * ejecutivo), más las acciones de admin: generar orden y subir comprobante.
 */
class PaymentNotificationsTest extends TestCase
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

    private function clientWith(string $email, ?string $whatsapp = null): Client
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole(RoleName::Client->value);

        return Client::factory()->create(['user_id' => $user->id, 'whatsapp' => $whatsapp]);
    }

    // ---------- Factura subida -> notifica al cliente ----------

    public function test_uploading_invoice_queues_client_notification(): void
    {
        Queue::fake();
        $this->actingAs($this->admin());
        $client = $this->clientWith('cliente@wortev.test');

        app(InvoiceService::class)->create(
            [
                'client_id' => $client->id, 'total' => '1160.00', 'status' => 'pending',
                'issued_at' => now()->toDateString(), 'due_at' => null, 'notes' => null,
            ],
            UploadedFile::fake()->create('factura.pdf', 50, 'application/pdf'),
        );

        Queue::assertPushed(SendInvoiceAvailableNotification::class);
    }

    public function test_invoice_notification_sends_mail_and_whatsapp_to_client(): void
    {
        $client = $this->clientWith('cliente@wortev.test', '+52 55 1111 2222');
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id, 'folio' => 'F-100', 'total_cents' => 116000,
        ]);

        $mail = Mockery::mock(MailGateway::class);
        $mail->shouldReceive('send')->once()->andReturn(GatewayResult::ok());
        $whatsapp = Mockery::mock(WhatsAppGateway::class);
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn(GatewayResult::ok());

        (new SendInvoiceAvailableNotification($invoice->id))->handle($mail, $whatsapp);
    }

    public function test_invoice_notification_without_whatsapp_only_sends_mail(): void
    {
        $client = $this->clientWith('cliente@wortev.test', null);
        $invoice = Invoice::factory()->create(['client_id' => $client->id]);

        $mail = Mockery::mock(MailGateway::class);
        $mail->shouldReceive('send')->once()->andReturn(GatewayResult::ok());
        $whatsapp = Mockery::mock(WhatsAppGateway::class);
        $whatsapp->shouldNotReceive('sendMessage');

        (new SendInvoiceAvailableNotification($invoice->id))->handle($mail, $whatsapp);
    }

    // ---------- Comprobante subido -> notifica al ejecutivo ----------

    private function orderWithRep(?SalesRep $rep, ?string $repPhone = null): PaymentOrder
    {
        $client = $this->clientWith('cli'.uniqid().'@wortev.test');
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'sales_rep_id' => $rep?->id,
            'unit_price_cents' => 50000, 'subtotal_cents' => 50000,
            'tax_cents' => 8000, 'tax_rate' => 16, 'total_cents' => 58000, 'currency' => 'MXN',
        ]);

        return app(PaymentOrderService::class)->createForService($service, new FakeMercadoPagoProvider());
    }

    public function test_client_proof_upload_queues_sales_rep_notification(): void
    {
        Queue::fake();
        $rep = SalesRep::factory()->create(['email' => 'rep@wortev.test', 'phone' => '+52 55 0000 1111']);
        $order = $this->orderWithRep($rep);

        app(\App\Domain\Billing\Services\PaymentProofService::class)->uploadByClient(
            $order,
            UploadedFile::fake()->create('c.pdf', 20, 'application/pdf'),
            'transfer', now()->toDateString(), 'REF-1',
        );

        Queue::assertPushed(SendPaymentProofNotification::class);
    }

    public function test_proof_notification_emails_and_whatsapps_the_sales_rep(): void
    {
        $rep = SalesRep::factory()->create(['email' => 'rep@wortev.test', 'phone' => '+52 55 0000 1111']);
        $order = $this->orderWithRep($rep);
        $proof = PaymentProof::create([
            'client_id' => $order->client_id,
            'payment_order_id' => $order->id,
            'file_path' => 'x.pdf', 'original_name' => 'x.pdf',
            'amount_cents' => 58000, 'currency' => 'MXN', 'method' => 'transfer',
            'status' => \App\Domain\Billing\Enums\PaymentProofStatus::Pending->value,
        ]);

        $mail = Mockery::mock(MailGateway::class);
        $mail->shouldReceive('send')->once()->andReturn(GatewayResult::ok());
        $whatsapp = Mockery::mock(WhatsAppGateway::class);
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn(GatewayResult::ok());

        (new SendPaymentProofNotification($proof->id))->handle($mail, $whatsapp);
    }

    public function test_proof_notification_does_nothing_without_sales_rep(): void
    {
        $order = $this->orderWithRep(null); // servicio sin ejecutivo
        $proof = PaymentProof::create([
            'client_id' => $order->client_id,
            'payment_order_id' => $order->id,
            'file_path' => 'x.pdf', 'original_name' => 'x.pdf',
            'amount_cents' => 58000, 'currency' => 'MXN', 'method' => 'transfer',
            'status' => \App\Domain\Billing\Enums\PaymentProofStatus::Pending->value,
        ]);

        $mail = Mockery::mock(MailGateway::class);
        $mail->shouldNotReceive('send');
        $whatsapp = Mockery::mock(WhatsAppGateway::class);
        $whatsapp->shouldNotReceive('sendMessage');

        (new SendPaymentProofNotification($proof->id))->handle($mail, $whatsapp);
    }

    // ---------- Acciones de admin ----------

    public function test_admin_can_generate_payment_order_for_client_service(): void
    {
        $client = $this->clientWith('c@wortev.test');
        $service = Service::factory()->create([
            'client_id' => $client->id, 'name' => 'Hosting Pro',
            'unit_price_cents' => 100000, 'subtotal_cents' => 100000,
            'tax_cents' => 16000, 'tax_rate' => 16, 'total_cents' => 116000, 'currency' => 'MXN',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentOrderIndex::class)
            ->call('toggleOrderForm')
            ->set('orderClientId', (string) $client->id)
            ->set('orderServiceId', (string) $service->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertSame(1, PaymentOrder::where('service_id', $service->id)->count());
    }

    public function test_admin_cannot_generate_order_for_service_of_another_client(): void
    {
        $client = $this->clientWith('c1@wortev.test');
        $otherService = Service::factory()->create(['client_id' => $this->clientWith('c2@wortev.test')->id]);

        Livewire::actingAs($this->admin())
            ->test(PaymentOrderIndex::class)
            ->call('toggleOrderForm')
            ->set('orderClientId', (string) $client->id)
            ->set('orderServiceId', (string) $otherService->id)
            ->call('createOrder')
            ->assertHasErrors('orderServiceId');

        $this->assertSame(0, PaymentOrder::count());
    }

    public function test_admin_uploads_proof_for_client_pending_review(): void
    {
        Queue::fake();
        $client = $this->clientWith('c@wortev.test');

        Livewire::actingAs($this->admin())
            ->test(PaymentOrderIndex::class)
            ->call('toggleProofForm')
            ->set('proofClientId', (string) $client->id)
            ->set('proofFile', UploadedFile::fake()->create('c.pdf', 30, 'application/pdf'))
            ->set('proofAmount', '580.00')
            ->set('proofMethod', 'transfer')
            ->call('submitProof')
            ->assertHasNoErrors();

        $proof = PaymentProof::where('client_id', $client->id)->firstOrFail();
        $this->assertSame(\App\Domain\Billing\Enums\PaymentProofStatus::Pending, $proof->status);
        $this->assertSame(58000, $proof->amount_cents);
        // Sin orden, pero avisa igual (si hubiera ejecutivo único).
        Queue::assertPushed(SendPaymentProofNotification::class);
    }

    public function test_admin_uploads_and_approves_proof_marking_order_paid(): void
    {
        $client = $this->clientWith('c@wortev.test');
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'unit_price_cents' => 50000, 'subtotal_cents' => 50000,
            'tax_cents' => 8000, 'tax_rate' => 16, 'total_cents' => 58000, 'currency' => 'MXN',
        ]);
        $order = app(PaymentOrderService::class)->createForService($service, new FakeMercadoPagoProvider());

        Livewire::actingAs($this->admin())
            ->test(PaymentOrderIndex::class)
            ->call('toggleProofForm')
            ->set('proofClientId', (string) $client->id)
            ->set('proofOrderId', (string) $order->id)
            ->set('proofFile', UploadedFile::fake()->create('c.pdf', 30, 'application/pdf'))
            ->set('proofAmount', '580.00')
            ->set('proofMethod', 'transfer')
            ->set('proofApproveNow', true)
            ->call('submitProof')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(PaymentOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->invoice_id);
    }

    public function test_support_role_cannot_upload_proof(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        Livewire::actingAs($support)
            ->test(PaymentOrderIndex::class)
            ->assertForbidden();
    }
}
