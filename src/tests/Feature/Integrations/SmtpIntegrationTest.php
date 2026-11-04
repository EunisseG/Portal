<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Notifications\Models\Setting;
use App\Integrations\Providers\Smtp\SmtpMailProvider;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Registry\IntegrationRegistry;
use App\Integrations\Mail\SmtpSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_is_in_the_catalog_with_mail_capability(): void
    {
        $registry = app(IntegrationRegistry::class);
        $manifest = $registry->manifest('smtp');

        $this->assertNotNull($manifest);
        $this->assertSame(IntegrationCapability::Mail, $manifest->capability);
    }

    public function test_registry_resolves_smtp_to_the_smtp_provider(): void
    {
        $integration = Integration::create([
            'key' => 'smtp',
            'name' => 'SMTP',
            'capability' => IntegrationCapability::Mail->value,
            'is_enabled' => true,
        ]);

        $provider = app(IntegrationRegistry::class)->resolve($integration);

        $this->assertInstanceOf(SmtpMailProvider::class, $provider);
        $this->assertSame(IntegrationCapability::Mail, $provider->capability());
    }

    public function test_test_connection_fails_when_smtp_not_configured(): void
    {
        $integration = Integration::create([
            'key' => 'smtp',
            'name' => 'SMTP',
            'capability' => IntegrationCapability::Mail->value,
            'is_enabled' => true,
        ]);

        $provider = app(IntegrationRegistry::class)->resolve($integration);
        $result = $provider->testConnection();

        // Sin host configurado, no intenta enviar.
        $this->assertFalse($result->success);
        $this->assertStringContainsString('no configurado', $result->message);
    }

    public function test_provider_reports_configured_when_settings_present(): void
    {
        Setting::put(SmtpSettings::KEY_HOST, 'mailpit');

        $integration = Integration::create([
            'key' => 'smtp',
            'name' => 'SMTP',
            'capability' => IntegrationCapability::Mail->value,
            'is_enabled' => true,
        ]);

        $provider = app(IntegrationRegistry::class)->resolve($integration);

        $this->assertTrue($provider->isConfigured());
    }
}
