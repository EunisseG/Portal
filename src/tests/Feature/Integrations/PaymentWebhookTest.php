<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Integrations\Jobs\ProcessInboundWebhook;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    // --- Mercado Pago -----------------------------------------------------

    private function mercadoPagoIntegration(string $secret): Integration
    {
        $integration = Integration::create([
            'key' => 'mercadopago',
            'name' => 'Mercado Pago',
            'capability' => IntegrationCapability::Payments->value,
            'is_enabled' => true,
        ]);
        IntegrationSecret::putFor($integration, 'webhook_secret', $secret);

        return $integration;
    }

    public function test_mercadopago_valid_webhook_is_accepted(): void
    {
        Queue::fake();
        $secret = 'mp-webhook-secret';
        $this->mercadoPagoIntegration($secret);

        $dataId = '123456';
        $requestId = 'req-abc';
        $ts = '1710000000';
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, $secret);

        $this->postJson('/api/webhooks/mercadopago?data.id='.$dataId, ['type' => 'payment'], [
            'x-signature' => "ts={$ts},v1={$v1}",
            'x-request-id' => $requestId,
        ])->assertStatus(202);

        $this->assertDatabaseHas('webhook_inbox', [
            'provider' => 'mercadopago',
            'external_event_id' => $dataId,
            'signature_valid' => true,
        ]);
        Queue::assertPushed(ProcessInboundWebhook::class);
    }

    public function test_mercadopago_invalid_signature_rejected(): void
    {
        Queue::fake();
        $this->mercadoPagoIntegration('mp-webhook-secret');

        $this->postJson('/api/webhooks/mercadopago?data.id=999', ['type' => 'payment'], [
            'x-signature' => 'ts=1,v1=deadbeef',
            'x-request-id' => 'req-x',
        ])->assertStatus(401);

        Queue::assertNothingPushed();
    }

    // --- Openpay ----------------------------------------------------------

    public function test_openpay_valid_basic_auth_webhook_is_accepted(): void
    {
        Queue::fake();
        $integration = Integration::create([
            'key' => 'openpay',
            'name' => 'Openpay',
            'capability' => IntegrationCapability::Payments->value,
            'is_enabled' => true,
        ]);
        IntegrationSecret::putFor($integration, 'webhook_secret', 'hook_user:hook_pass');

        $basic = 'Basic '.base64_encode('hook_user:hook_pass');

        $this->postJson('/api/webhooks/openpay', ['id' => 'evt-op-1', 'type' => 'charge.succeeded'], [
            'Authorization' => $basic,
        ])->assertStatus(202);

        $this->assertDatabaseHas('webhook_inbox', [
            'provider' => 'openpay',
            'external_event_id' => 'evt-op-1',
        ]);
        Queue::assertPushed(ProcessInboundWebhook::class);
    }

    public function test_openpay_wrong_basic_auth_rejected(): void
    {
        Queue::fake();
        $integration = Integration::create([
            'key' => 'openpay',
            'name' => 'Openpay',
            'capability' => IntegrationCapability::Payments->value,
            'is_enabled' => true,
        ]);
        IntegrationSecret::putFor($integration, 'webhook_secret', 'hook_user:hook_pass');

        $this->postJson('/api/webhooks/openpay', ['id' => 'evt-op-2'], [
            'Authorization' => 'Basic '.base64_encode('hook_user:WRONG'),
        ])->assertStatus(401);

        Queue::assertNothingPushed();
    }

    // --- PayPal -----------------------------------------------------------

    private function paypalIntegration(): Integration
    {
        $integration = Integration::create([
            'key' => 'paypal',
            'name' => 'PayPal',
            'capability' => IntegrationCapability::Payments->value,
            'is_enabled' => true,
            'config' => ['client_id' => 'AY-client', 'sandbox' => true],
        ]);
        IntegrationSecret::putFor($integration, 'client_secret', 'EL-secret');
        IntegrationSecret::putFor($integration, 'webhook_id', 'WH-123');

        return $integration;
    }

    public function test_paypal_verified_webhook_is_accepted(): void
    {
        Queue::fake();
        $this->paypalIntegration();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);

        $this->postJson('/api/webhooks/paypal', ['id' => 'WH-EVT-1', 'event_type' => 'CHECKOUT.ORDER.APPROVED'], [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-CERT-URL' => 'https://paypal.com/cert',
            'PAYPAL-TRANSMISSION-ID' => 'tid-1',
            'PAYPAL-TRANSMISSION-SIG' => 'sig',
            'PAYPAL-TRANSMISSION-TIME' => '2026-01-01T00:00:00Z',
        ])->assertStatus(202);

        $this->assertDatabaseHas('webhook_inbox', [
            'provider' => 'paypal',
            'external_event_id' => 'WH-EVT-1',
        ]);
        Queue::assertPushed(ProcessInboundWebhook::class);
    }

    public function test_paypal_unverified_webhook_rejected(): void
    {
        Queue::fake();
        $this->paypalIntegration();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'FAILURE',
            ], 200),
        ]);

        $this->postJson('/api/webhooks/paypal', ['id' => 'WH-EVT-2'], [
            'PAYPAL-TRANSMISSION-ID' => 'tid-2',
        ])->assertStatus(401);

        Queue::assertNothingPushed();
    }
}
