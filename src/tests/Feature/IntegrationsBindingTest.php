<?php

namespace Tests\Feature;

use App\Integrations\Contracts\DomainGateway;
use App\Integrations\Contracts\HostingGateway;
use App\Integrations\Contracts\MailGateway;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Contracts\WhatsAppGateway;
use App\Integrations\Mail\LoggingMailGateway;
use App\Integrations\Providers\MercadoPago\FakeMercadoPagoProvider;
use App\Integrations\Providers\Namecheap\FakeNamecheapDomainProvider;
use App\Integrations\Providers\WhatsApp\FakeWhatsAppProvider;
use App\Integrations\Providers\Whm\FakeWhmHostingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationsBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateways_resolve_to_fake_implementations(): void
    {
        // El correo se resuelve al decorator que registra los envíos y envuelve
        // la implementación fake.
        $this->assertInstanceOf(LoggingMailGateway::class, app(MailGateway::class));
        // WhatsApp y WHM sin integración configurada resuelven a sus fakes nuevos.
        $this->assertInstanceOf(FakeWhatsAppProvider::class, app(WhatsAppGateway::class));
        $this->assertInstanceOf(FakeWhmHostingProvider::class, app(HostingGateway::class));
        $this->assertInstanceOf(FakeNamecheapDomainProvider::class, app(DomainGateway::class));
        $this->assertInstanceOf(FakeMercadoPagoProvider::class, app(PaymentGateway::class));
    }

    public function test_fake_mail_gateway_reports_success(): void
    {
        $result = app(MailGateway::class)->sendTestEmail('destino@wortev.test');

        $this->assertTrue($result->success);
    }
}
