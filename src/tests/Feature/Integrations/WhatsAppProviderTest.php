<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Integrations\Contracts\WhatsAppGateway;
use App\Integrations\Providers\WhatsApp\FakeWhatsAppProvider;
use App\Integrations\Providers\WhatsApp\WhatsAppCloudProvider;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppProviderTest extends TestCase
{
    use RefreshDatabase;

    private function whatsappIntegration(bool $configured = true, bool $enabled = true): Integration
    {
        $integration = Integration::create([
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'capability' => IntegrationCapability::Notifications->value,
            'is_enabled' => $enabled,
            'config' => $configured ? ['phone_number_id' => '123456', 'template_language' => 'es_MX'] : [],
        ]);

        if ($configured) {
            IntegrationSecret::putFor($integration, 'access_token', 'meta-token-xyz');
        }

        return $integration;
    }

    public function test_sends_template_message_to_meta_cloud_api(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.123']]], 200),
        ]);

        $provider = new WhatsAppCloudProvider($this->whatsappIntegration());
        $result = $provider->sendMessage('+52 55 1234 5678', 'renewal_reminder', [
            'item' => 'Hosting Pro',
            'due_date' => '2026-06-01',
        ]);

        $this->assertTrue($result->success);
        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), '/123456/messages')
                && $request->hasHeader('Authorization', 'Bearer meta-token-xyz')
                && $body['type'] === 'template'
                && $body['template']['name'] === 'renewal_reminder'
                && $body['to'] === '525512345678'; // normalizado a solo dígitos
        });
    }

    public function test_does_not_send_when_not_configured(): void
    {
        Http::fake();

        $provider = new WhatsAppCloudProvider($this->whatsappIntegration(configured: false));
        $result = $provider->sendMessage('5512345678', 'renewal_reminder');

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }

    public function test_binding_resolves_real_provider_when_configured(): void
    {
        $this->whatsappIntegration();

        $this->assertInstanceOf(WhatsAppCloudProvider::class, app(WhatsAppGateway::class));
    }

    public function test_binding_resolves_fake_when_not_configured(): void
    {
        // Sin integración whatsapp activa/configurada → fake.
        $this->assertInstanceOf(FakeWhatsAppProvider::class, app(WhatsAppGateway::class));
    }

    public function test_binding_resolves_fake_when_disabled(): void
    {
        $this->whatsappIntegration(enabled: false);

        $this->assertInstanceOf(FakeWhatsAppProvider::class, app(WhatsAppGateway::class));
    }

    public function test_http_error_returns_failure(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'bad'], 400)]);

        $provider = new WhatsAppCloudProvider($this->whatsappIntegration());
        $result = $provider->sendMessage('5512345678', 'renewal_reminder');

        $this->assertFalse($result->success);
    }
}
