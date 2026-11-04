<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_exposes_the_catalog(): void
    {
        $registry = app(IntegrationRegistry::class);

        $this->assertTrue($registry->has('monday'));
        $this->assertTrue($registry->has('smtp'));
        $this->assertTrue($registry->has('custom_api'));

        $monday = $registry->manifest('monday');
        $this->assertSame(IntegrationCapability::Tickets, $monday->capability);
        $this->assertContains('TicketCreated', $monday->events);

        // El catálogo lista todos los proveedores previstos.
        $keys = $registry->catalog()->pluck('key');
        foreach (['monday', 'whatsapp', 'smtp', 'whm', 'domains', 'mercadopago', 'openpay', 'paypal', 'slack', 'custom_api'] as $k) {
            $this->assertTrue($keys->contains($k), "Falta el proveedor {$k} en el catálogo.");
        }

        // Google y Microsoft se quitaron del catálogo (sin caso de uso).
        $this->assertFalse($keys->contains('google'));
        $this->assertFalse($keys->contains('microsoft'));
    }

    public function test_secret_is_stored_encrypted_and_masked(): void
    {
        $integration = Integration::create([
            'key' => 'monday',
            'name' => 'Monday.com',
            'capability' => IntegrationCapability::Tickets->value,
        ]);

        $secret = IntegrationSecret::putFor($integration, 'api_token', 'super-secreto-1234');

        // El valor crudo NO es el texto plano.
        $this->assertNotSame('super-secreto-1234', $secret->getRawOriginal('value'));
        // Se descifra correctamente.
        $this->assertSame('super-secreto-1234', $secret->reveal());
        // Enmascarado muestra solo los últimos 4.
        $this->assertSame('••••••1234', $secret->masked());
        // La serialización oculta el valor.
        $this->assertArrayNotHasKey('value', $secret->toArray());
    }

    public function test_record_integration_log_writes_metadata_only(): void
    {
        $integration = Integration::create([
            'key' => 'monday',
            'name' => 'Monday.com',
            'capability' => IntegrationCapability::Tickets->value,
        ]);

        app(RecordIntegrationLog::class)->record('ticket.created', [
            'integration_id' => $integration->id,
            'success' => true,
            'http_status' => 201,
            'duration_ms' => 87,
            'internal_reference' => 'Ticket:1025',
            'external_reference' => 'MondayItem:458721',
        ]);

        $this->assertDatabaseHas('integration_logs', [
            'event' => 'ticket.created',
            'direction' => 'outbound',
            'success' => true,
            'http_status' => 201,
            'internal_reference' => 'Ticket:1025',
            'external_reference' => 'MondayItem:458721',
        ]);

        $log = IntegrationLog::first();
        $this->assertNotNull($log->created_at);
    }

    public function test_integration_relationships_and_casts(): void
    {
        $integration = Integration::create([
            'key' => 'smtp',
            'name' => 'SMTP',
            'capability' => IntegrationCapability::Mail->value,
            'is_enabled' => true,
            'config' => ['host' => 'mailpit', 'port' => 1025],
        ]);

        $this->assertInstanceOf(IntegrationCapability::class, $integration->capability);
        $this->assertTrue($integration->isActive());
        $this->assertSame('mailpit', $integration->config['host']);
    }
}
