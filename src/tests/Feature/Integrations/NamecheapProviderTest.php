<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Integrations\Providers\Namecheap\FakeNamecheapDomainProvider;
use App\Integrations\Providers\Namecheap\NamecheapDomainProvider;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NamecheapProviderTest extends TestCase
{
    use RefreshDatabase;

    private function domainsIntegration(bool $configured = true, bool $enabled = true): Integration
    {
        $integration = Integration::create([
            'key' => 'domains',
            'name' => 'Dominios (Namecheap)',
            'capability' => IntegrationCapability::Domains->value,
            'is_enabled' => $enabled,
            'config' => $configured ? [
                'api_user' => 'wortev',
                'username' => 'wortev',
                'client_ip' => '200.10.10.10',
                'sandbox' => true,
            ] : [],
        ]);

        if ($configured) {
            IntegrationSecret::putFor($integration, 'api_key', 'nc-key-abc123');
        }

        return $integration;
    }

    private function okXml(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<ApiResponse Status="OK" xmlns="http://api.namecheap.com/xml.response">'
            .'<CommandResponse Type="namecheap.domains.create">'
            .'<DomainCreateResult Domain="cliente.com" Registered="true"/>'
            .'</CommandResponse></ApiResponse>';
    }

    private function errorXml(string $message): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<ApiResponse Status="ERROR" xmlns="http://api.namecheap.com/xml.response">'
            .'<Errors><Error Number="2019166">'.$message.'</Error></Errors>'
            .'<CommandResponse/></ApiResponse>';
    }

    public function test_real_provider_registers_domain_via_api(): void
    {
        Http::fake([
            'api.sandbox.namecheap.com/*' => Http::response($this->okXml(), 200),
        ]);

        $provider = new NamecheapDomainProvider($this->domainsIntegration());
        $result = $provider->register('cliente.com', 2);

        $this->assertTrue($result->success);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.sandbox.namecheap.com')
                && str_contains($request->url(), 'Command=namecheap.domains.create')
                && str_contains($request->url(), 'DomainName=cliente.com')
                && str_contains($request->url(), 'ApiKey=nc-key-abc123')
                && str_contains($request->url(), 'ClientIp=200.10.10.10');
        });
    }

    public function test_real_provider_reports_api_error(): void
    {
        Http::fake([
            'api.sandbox.namecheap.com/*' => Http::response($this->errorXml('Domain is not available'), 200),
        ]);

        $provider = new NamecheapDomainProvider($this->domainsIntegration());
        $result = $provider->register('taken.com');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Domain is not available', $result->message);
    }

    public function test_real_provider_does_not_call_api_when_not_configured(): void
    {
        Http::fake();

        $provider = new NamecheapDomainProvider($this->domainsIntegration(configured: false));
        $result = $provider->renew('cliente.com');

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }

    public function test_registry_resolves_real_when_configured(): void
    {
        $this->assertInstanceOf(
            NamecheapDomainProvider::class,
            app(IntegrationRegistry::class)->resolve($this->domainsIntegration())
        );
    }

    public function test_registry_resolves_fake_when_not_configured(): void
    {
        $this->assertInstanceOf(
            FakeNamecheapDomainProvider::class,
            app(IntegrationRegistry::class)->resolve($this->domainsIntegration(configured: false))
        );
    }
}
