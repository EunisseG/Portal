<?php

namespace Tests\Feature\Billing;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Services\PaymentOrderService;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientMemberService;
use App\Domain\Notifications\Models\Setting;
use App\Domain\Services\Enums\BillingMode;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\Product;
use App\Domain\Services\Models\ProductPrice;
use App\Domain\Services\Models\Service;
use App\Integrations\Providers\MercadoPago\FakeMercadoPagoProvider;
use App\Livewire\Client\Invoices\InvoiceList;
use App\Livewire\Client\Invoices\PaymentOrderReceipt;
use App\Livewire\Client\Orders\OrderCreate;
use App\Models\User;
use App\Support\OperationMode;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedidos del cliente y la regla de modo: en manual solo se crea la orden; en
 * automático se conecta con la pasarela (también para órdenes ya existentes).
 */
class ClientOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    private function automaticMode(): void
    {
        Setting::put(OperationMode::KEY_PAYMENTS, OperationMode::MODE_AUTOMATIC);
    }

    private function pedido(Client $client, int $total = 116000): Service
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
        ]);
    }

    private function oneTimeProduct(): Product
    {
        return Product::create([
            'name' => 'Respaldo diario',
            'billing_mode' => BillingMode::OneTime->value,
            'price_cents' => 20000,
            'currency' => 'MXN',
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // Regla de modo manual / automático
    // ------------------------------------------------------------------

    public function test_manual_mode_creates_order_without_payment_link(): void
    {
        $service = $this->pedido(Client::factory()->create());

        $order = app(PaymentOrderService::class)->createForService($service, new FakeMercadoPagoProvider());

        $this->assertSame(PaymentOrderStatus::Pending, $order->status);
        $this->assertNull($order->checkout_url);
        $this->assertNull($order->preference_id);
        // La referencia queda como el folio, listo para conciliar después.
        $this->assertSame($order->folio, $order->external_reference);
    }

    public function test_automatic_mode_creates_order_with_payment_link(): void
    {
        $this->automaticMode();
        $service = $this->pedido(Client::factory()->create());

        $order = app(PaymentOrderService::class)->createForService($service, new FakeMercadoPagoProvider());

        $this->assertNotEmpty($order->checkout_url);
    }

    public function test_order_created_in_manual_gets_payment_link_when_mode_turns_automatic(): void
    {
        $service = $this->pedido(Client::factory()->create());
        $svc = app(PaymentOrderService::class);
        $order = $svc->createForService($service, new FakeMercadoPagoProvider());
        $this->assertNull($order->checkout_url);

        // En manual, ensureCheckout no hace nada.
        $this->assertNull($svc->ensureCheckout($order, new FakeMercadoPagoProvider())->checkout_url);

        // Al pasar a automático, ahora sí se conecta con la pasarela.
        $this->automaticMode();
        $order = $svc->ensureCheckout($order->fresh(), new FakeMercadoPagoProvider());

        $this->assertNotEmpty($order->checkout_url);
    }

    public function test_client_pay_button_connects_to_gateway_for_order_created_in_manual(): void
    {
        $me = $this->clientUser();
        $order = app(PaymentOrderService::class)
            ->createForService($this->pedido($me->client), new FakeMercadoPagoProvider());
        $this->assertNull($order->checkout_url);

        $this->automaticMode();

        Livewire::actingAs($me)
            ->test(PaymentOrderReceipt::class, ['order' => $order])
            ->assertSee('Realizar pago')
            ->call('pay')
            ->assertRedirect();

        $this->assertNotEmpty($order->fresh()->checkout_url);
    }

    // ------------------------------------------------------------------
    // Pedido del cliente
    // ------------------------------------------------------------------

    public function test_client_places_order_and_gets_payment_order_without_gateway_in_manual_mode(): void
    {
        $me = $this->clientUser();
        $product = $this->oneTimeProduct();

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $product->id)
            ->call('place')
            ->assertHasNoErrors()
            ->assertRedirect();

        $service = Service::where('client_id', $me->client->id)->firstOrFail();
        $this->assertSame('Respaldo diario', $service->name);
        $this->assertSame(ServiceStatus::Pending, $service->status);

        $order = PaymentOrder::where('client_id', $me->client->id)->firstOrFail();
        $this->assertSame($service->id, $order->service_id);
        $this->assertSame(PaymentOrderStatus::Pending, $order->status);
        $this->assertSame(20000, $order->amount_cents);
        $this->assertNull($order->checkout_url); // manual: sin pasarela
    }

    public function test_client_order_in_automatic_mode_gets_payment_link(): void
    {
        $this->automaticMode();
        $me = $this->clientUser();
        $product = $this->oneTimeProduct();

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $product->id)
            ->call('place');

        $order = PaymentOrder::where('client_id', $me->client->id)->firstOrFail();
        $this->assertNotEmpty($order->checkout_url);
    }

    public function test_client_orders_recurring_product_with_chosen_cycle(): void
    {
        $me = $this->clientUser();
        $product = Product::create([
            'name' => 'Hosting Pro',
            'billing_mode' => BillingMode::Recurring->value,
            'price_cents' => 0,
            'currency' => 'MXN',
            'is_active' => true,
        ]);
        ProductPrice::create([
            'product_id' => $product->id,
            'billing_cycle' => 'annual',
            'unit_price_cents' => 100000,
            'tax_rate' => 16,
            'currency' => 'MXN',
        ]);

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $product->id)
            ->assertSet('cycle', 'annual')
            ->call('place')
            ->assertHasNoErrors();

        $order = PaymentOrder::where('client_id', $me->client->id)->firstOrFail();
        $this->assertSame(116000, $order->amount_cents);
        $this->assertSame(100000, $order->subtotal_cents);
        $this->assertSame(16000, $order->tax_cents);
        $this->assertSame('annual', $order->service->billing_cycle->value);
    }

    public function test_client_cannot_order_inactive_product(): void
    {
        $me = $this->clientUser();
        $product = $this->oneTimeProduct();
        $product->update(['is_active' => false]);

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $product->id)
            ->call('place')
            ->assertHasErrors('productId');

        $this->assertSame(0, Service::where('client_id', $me->client->id)->count());
        $this->assertSame(0, PaymentOrder::count());
    }

    public function test_sub_user_without_orders_permission_cannot_order(): void
    {
        $owner = $this->clientUser();
        $member = app(ClientMemberService::class)->create($owner->client, [
            'name' => 'Colab',
            'email' => 'colab@wortev.test',
            'permissions' => ['client.services.view'], // sin client.orders.create
        ]);

        Livewire::actingAs($member->refresh())
            ->test(OrderCreate::class)
            ->assertForbidden();

        // El titular siempre puede.
        Livewire::actingAs($owner)
            ->test(OrderCreate::class)
            ->assertOk();
    }

    public function test_sub_user_with_orders_permission_can_order(): void
    {
        $owner = $this->clientUser();
        $member = app(ClientMemberService::class)->create($owner->client, [
            'name' => 'Colab',
            'email' => 'colab2@wortev.test',
            'permissions' => ['client.orders.create'],
        ]);

        Livewire::actingAs($member->refresh())
            ->test(OrderCreate::class)
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // Hosting: pregunta por el dominio
    // ------------------------------------------------------------------

    private function hostingProduct(): Product
    {
        return Product::create([
            'name' => 'Hosting Pro',
            'type' => 'hosting',
            'billing_mode' => BillingMode::OneTime->value,
            'price_cents' => 100000,
            'currency' => 'MXN',
            'is_active' => true,
        ]);
    }

    public function test_domain_questions_only_appear_for_hosting(): void
    {
        $me = $this->clientUser();
        $hosting = $this->hostingProduct();
        $other = $this->oneTimeProduct();

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $hosting->id)
            ->assertSee('Nombre del dominio')
            ->set('productId', (string) $other->id)
            ->assertDontSee('Nombre del dominio');
    }

    public function test_hosting_order_requires_a_domain_answer(): void
    {
        $me = $this->clientUser();
        $hosting = $this->hostingProduct();

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $hosting->id)
            ->call('place')
            ->assertHasErrors('domainName');

        $this->assertSame(0, Service::where('client_id', $me->client->id)->count());
        $this->assertSame(0, PaymentOrder::count());
    }

    public function test_hosting_order_rejects_invalid_domain_name(): void
    {
        $me = $this->clientUser();
        $hosting = $this->hostingProduct();

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $hosting->id)
            ->set('domainName', 'no es un dominio')
            ->call('place')
            ->assertHasErrors('domainName');

        $this->assertSame(0, Service::where('client_id', $me->client->id)->count());
    }

    public function test_hosting_with_domain_already_owned_elsewhere(): void
    {
        $me = $this->clientUser();
        $hosting = $this->hostingProduct();

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $hosting->id)
            ->set('domainName', 'MiEmpresa.com')
            ->set('domainOrigin', 'owned')
            ->call('place')
            ->assertHasNoErrors();

        $service = Service::where('client_id', $me->client->id)->firstOrFail();
        $this->assertSame(\App\Domain\Services\Enums\ServiceType::Hosting, $service->type);

        $domain = \App\Domain\Services\Models\Domain::where('service_id', $service->id)->firstOrFail();
        $this->assertSame('miempresa.com', $domain->name);
        $this->assertSame($me->client->id, $domain->client_id);
        $this->assertStringContainsString('otro registrador', $domain->notes);
        // El dominio propio no suma costo: solo el precio del hosting.
        $this->assertSame(100000, PaymentOrder::where('service_id', $service->id)->firstOrFail()->amount_cents);
    }

    public function test_hosting_buying_free_extension_adds_no_cost(): void
    {
        $me = $this->clientUser();
        $hosting = $this->hostingProduct();

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $hosting->id)
            ->set('domainName', 'nueva.com.mx')
            ->set('domainOrigin', 'purchase')
            ->call('place')
            ->assertHasNoErrors();

        $service = Service::where('client_id', $me->client->id)->firstOrFail();
        $domain = \App\Domain\Services\Models\Domain::where('service_id', $service->id)->firstOrFail();
        $this->assertStringContainsString('gratis', $domain->notes);
        $this->assertSame(100000, PaymentOrder::where('service_id', $service->id)->firstOrFail()->amount_cents);
    }

    public function test_hosting_buying_chargeable_extension_is_marked_for_quote_not_charged(): void
    {
        $me = $this->clientUser();
        $hosting = $this->hostingProduct();

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $hosting->id)
            ->set('domainName', 'nueva.net')
            ->set('domainOrigin', 'purchase')
            ->assertSee('se cotiza aparte')
            ->call('place')
            ->assertHasNoErrors();

        $service = Service::where('client_id', $me->client->id)->firstOrFail();
        $domain = \App\Domain\Services\Models\Domain::where('service_id', $service->id)->firstOrFail();
        $this->assertStringContainsString('cotización', $domain->notes);
        // No se inventa un precio: el pedido queda solo con el hosting.
        $this->assertSame(100000, PaymentOrder::where('service_id', $service->id)->firstOrFail()->amount_cents);
    }

    public function test_hosting_can_link_an_existing_unassigned_domain(): void
    {
        $me = $this->clientUser();
        $hosting = $this->hostingProduct();
        $mine = \App\Domain\Services\Models\Domain::factory()->create([
            'client_id' => $me->client->id, 'service_id' => null, 'name' => 'ya-mio.com',
        ]);

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $hosting->id)
            ->assertSet('domainMode', 'existing')
            ->set('domainId', (string) $mine->id)
            ->call('place')
            ->assertHasNoErrors();

        $service = Service::where('client_id', $me->client->id)->firstOrFail();
        $this->assertSame($service->id, $mine->fresh()->service_id);
    }

    public function test_hosting_cannot_link_another_clients_domain(): void
    {
        $me = $this->clientUser();
        $hosting = $this->hostingProduct();
        $theirs = \App\Domain\Services\Models\Domain::factory()->create([
            'client_id' => Client::factory()->create()->id, 'service_id' => null, 'name' => 'ajeno.com',
        ]);

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $hosting->id)
            ->set('domainMode', 'existing')
            ->set('domainId', (string) $theirs->id)
            ->call('place')
            ->assertHasErrors('domainId');

        $this->assertNull($theirs->fresh()->service_id);
        $this->assertSame(0, Service::where('client_id', $me->client->id)->count());
    }

    public function test_non_hosting_product_ignores_domain_fields(): void
    {
        $me = $this->clientUser();
        $product = $this->oneTimeProduct();

        Livewire::actingAs($me)
            ->test(OrderCreate::class)
            ->set('productId', (string) $product->id)
            ->set('domainName', 'sobra.com')
            ->call('place')
            ->assertHasNoErrors();

        $this->assertSame(0, \App\Domain\Services\Models\Domain::where('name', 'sobra.com')->count());
    }

    // ------------------------------------------------------------------
    // Dónde aparecen las órdenes
    // ------------------------------------------------------------------

    public function test_orders_show_in_payment_proofs_tab_and_not_in_invoices_tab(): void
    {
        $me = $this->clientUser();
        $order = app(PaymentOrderService::class)
            ->createForService($this->pedido($me->client), new FakeMercadoPagoProvider());

        // Pestaña de facturas: solo facturas, sin órdenes.
        Livewire::actingAs($me)
            ->test(InvoiceList::class)
            ->assertSet('tab', 'invoices')
            ->assertDontSee($order->folio);

        // Página de comprobantes de pago: aquí viven las órdenes.
        Livewire::actingAs($me)
            ->test(InvoiceList::class, ['section' => 'proofs'])
            ->assertSee('Comprobantes de pago')
            ->assertSee($order->folio)
            ->assertSee('Ver recibo');
    }

    public function test_old_orders_url_redirects_to_payment_proofs_page(): void
    {
        $me = $this->clientUser();

        $this->actingAs($me)
            ->get('/client/ordenes-pago')
            ->assertRedirect('/client/comprobantes');
    }

    public function test_client_menu_has_payments_group_with_invoices_and_proofs(): void
    {
        $me = $this->clientUser();

        $this->actingAs($me)
            ->get('/client')
            ->assertOk()
            ->assertSee('Pagos')
            ->assertSee(route('client.invoices.index'), false)
            ->assertSee(route('client.proofs.index'), false)
            ->assertSee('Comprobantes de pago');
    }

    public function test_invoices_and_proofs_are_separate_pages(): void
    {
        $me = $this->clientUser();
        $order = app(PaymentOrderService::class)
            ->createForService($this->pedido($me->client), new FakeMercadoPagoProvider());

        $this->actingAs($me)->get('/client/facturas')
            ->assertOk()
            ->assertSee('Mis facturas')
            ->assertDontSee($order->folio);

        $this->actingAs($me)->get('/client/comprobantes')
            ->assertOk()
            ->assertSee($order->folio);
    }
}
