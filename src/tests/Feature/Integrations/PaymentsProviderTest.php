<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Providers\MercadoPago\FakeMercadoPagoProvider;
use App\Integrations\Providers\MercadoPago\MercadoPagoProvider;
use App\Integrations\Providers\Openpay\FakeOpenpayProvider;
use App\Integrations\Providers\Openpay\OpenpayProvider;
use App\Integrations\Providers\PayPal\FakePayPalProvider;
use App\Integrations\Providers\PayPal\PayPalProvider;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentsProviderTest extends TestCase
{
    use RefreshDatabase;

    // --- Mercado Pago -----------------------------------------------------

    private function mercadoPago(bool $configured = true, bool $enabled = true): Integration
    {
        $integration = Integration::create([
            'key' => 'mercadopago',
            'name' => 'Mercado Pago',
            'capability' => IntegrationCapability::Payments->value,
            'is_enabled' => $enabled,
            'config' => ['currency' => 'MXN'],
        ]);

        if ($configured) {
            IntegrationSecret::putFor($integration, 'access_token', 'APP_USR-mp-token');
        }

        return $integration;
    }

    public function test_mercadopago_creates_preference(): void
    {
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref-123',
                'init_point' => 'https://mp.com/checkout/pref-123',
            ], 201),
        ]);

        $provider = new MercadoPagoProvider($this->mercadoPago());
        $result = $provider->createCharge(['amount' => 500, 'title' => 'Plan', 'reference' => 'INV-1']);

        $this->assertTrue($result->success);
        $this->assertSame('pref-123', $result->data['preference_id']);
        $this->assertSame('https://mp.com/checkout/pref-123', $result->data['checkout_url']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer APP_USR-mp-token')
            && str_contains($r->url(), 'checkout/preferences'));
    }

    public function test_mercadopago_reports_error(): void
    {
        Http::fake([
            'api.mercadopago.com/*' => Http::response(['message' => 'invalid token'], 401),
        ]);

        $result = (new MercadoPagoProvider($this->mercadoPago()))->createCharge(['amount' => 10]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('invalid token', $result->message);
    }

    public function test_mercadopago_not_configured_does_not_call_api(): void
    {
        Http::fake();
        $result = (new MercadoPagoProvider($this->mercadoPago(configured: false)))->createCharge(['amount' => 10]);

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }

    // --- Openpay ----------------------------------------------------------

    private function openpay(bool $configured = true): Integration
    {
        $integration = Integration::create([
            'key' => 'openpay',
            'name' => 'Openpay',
            'capability' => IntegrationCapability::Payments->value,
            'is_enabled' => true,
            'config' => ['merchant_id' => 'mzdtln0bmtms6o3kck8f', 'sandbox' => true],
        ]);

        if ($configured) {
            IntegrationSecret::putFor($integration, 'private_key', 'sk_openpay_private');
        }

        return $integration;
    }

    public function test_openpay_creates_store_charge(): void
    {
        Http::fake([
            'sandbox-api.openpay.mx/*' => Http::response([
                'id' => 'trxyz',
                'status' => 'in_progress',
                'payment_method' => ['reference' => '010101010101', 'barcode_url' => 'https://x/bc.png'],
            ], 200),
        ]);

        $provider = new OpenpayProvider($this->openpay());
        $result = $provider->createCharge(['amount' => 300, 'description' => 'Plan', 'reference' => 'INV-2']);

        $this->assertTrue($result->success);
        $this->assertSame('trxyz', $result->data['charge_id']);
        $this->assertSame('010101010101', $result->data['payment_reference']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sandbox-api.openpay.mx/v1/mzdtln0bmtms6o3kck8f/charges'));
    }

    public function test_openpay_reports_error(): void
    {
        Http::fake([
            'sandbox-api.openpay.mx/*' => Http::response(['description' => 'The amount is required'], 400),
        ]);

        $result = (new OpenpayProvider($this->openpay()))->createCharge([]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('The amount is required', $result->message);
    }

    // --- PayPal -----------------------------------------------------------

    private function paypal(bool $configured = true): Integration
    {
        $integration = Integration::create([
            'key' => 'paypal',
            'name' => 'PayPal',
            'capability' => IntegrationCapability::Payments->value,
            'is_enabled' => true,
            'config' => ['client_id' => 'AY-client-id', 'sandbox' => true, 'currency' => 'USD'],
        ]);

        if ($configured) {
            IntegrationSecret::putFor($integration, 'client_secret', 'EL-secret');
        }

        return $integration;
    }

    public function test_paypal_creates_order_after_oauth(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21AA-token'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-9',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://x/self'],
                    ['rel' => 'approve', 'href' => 'https://paypal.com/checkoutnow?token=ORDER-9'],
                ],
            ], 201),
        ]);

        $provider = new PayPalProvider($this->paypal());
        $result = $provider->createCharge(['amount' => 49.9, 'reference' => 'INV-3']);

        $this->assertTrue($result->success);
        $this->assertSame('ORDER-9', $result->data['order_id']);
        $this->assertSame('https://paypal.com/checkoutnow?token=ORDER-9', $result->data['checkout_url']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/oauth2/token'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/checkout/orders')
            && $r->hasHeader('Authorization', 'Bearer A21AA-token'));
    }

    public function test_paypal_fails_when_token_cannot_be_obtained(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $result = (new PayPalProvider($this->paypal()))->createCharge(['amount' => 10]);

        $this->assertFalse($result->success);
    }

    // --- Registry / binding ----------------------------------------------

    public function test_registry_resolves_real_and_fake_per_provider(): void
    {
        $registry = app(IntegrationRegistry::class);

        $this->assertInstanceOf(MercadoPagoProvider::class, $registry->resolve($this->mercadoPago()));
        $this->assertInstanceOf(OpenpayProvider::class, $registry->resolve($this->openpay()));
        $this->assertInstanceOf(PayPalProvider::class, $registry->resolve($this->paypal()));
    }

    public function test_payment_gateway_binding_prefers_configured_provider(): void
    {
        // Openpay activo pero sin credenciales -> fake; luego se activa PayPal
        // configurado -> el binding debe devolver el real de PayPal.
        $this->openpay(configured: false);
        $this->assertInstanceOf(FakeOpenpayProvider::class, app(PaymentGateway::class));

        $this->paypal();
        $this->app->forgetInstance(PaymentGateway::class);
        $this->assertInstanceOf(PayPalProvider::class, app(PaymentGateway::class));
    }
}
