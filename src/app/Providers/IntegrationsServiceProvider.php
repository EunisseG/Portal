<?php

namespace App\Providers;

use App\Integrations\Contracts\DomainGateway;
use App\Integrations\Contracts\HostingGateway;
use App\Integrations\Contracts\MailGateway;
use App\Integrations\Contracts\NotificationGateway;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Contracts\WhatsAppGateway;
use App\Integrations\Mail\FakeMailGateway;
use App\Integrations\Mail\LoggingMailGateway;
use App\Integrations\Providers\FakeGenericProvider;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Registry\IntegrationManifest;
use App\Integrations\Registry\IntegrationRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaza cada interfaz de integración con su implementación.
 *
 * Por ahora todas apuntan a implementaciones Fake (sin conexiones reales).
 * Cuando se integre un proveedor real, basta con cambiar el binding aquí,
 * sin tocar el resto de la aplicación.
 *
 * Laravel registra automáticamente los pares definidos en la propiedad
 * pública $bindings (contrato => implementación).
 */
class IntegrationsServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [];

    /**
     * Proveedores de pago disponibles: clave de integración => [real, fake].
     * El binding de PaymentGateway usa el primero activo y configurado.
     *
     * @var array<string, array{0: class-string, 1: class-string}>
     */
    private array $paymentProviders = [
        'mercadopago' => [
            \App\Integrations\Providers\MercadoPago\MercadoPagoProvider::class,
            \App\Integrations\Providers\MercadoPago\FakeMercadoPagoProvider::class,
        ],
        'openpay' => [
            \App\Integrations\Providers\Openpay\OpenpayProvider::class,
            \App\Integrations\Providers\Openpay\FakeOpenpayProvider::class,
        ],
        'paypal' => [
            \App\Integrations\Providers\PayPal\PayPalProvider::class,
            \App\Integrations\Providers\PayPal\FakePayPalProvider::class,
        ],
    ];

    public function register(): void
    {
        // WhatsApp: usa el provider real (Cloud API de Meta) si la integración
        // 'whatsapp' está activa y configurada; si no, el fake. Los jobs piden
        // WhatsAppGateway y obtienen automáticamente el adecuado.
        $this->app->bind(WhatsAppGateway::class, function ($app) {
            $integration = \App\Domain\Integrations\Models\Integration::where('key', 'whatsapp')
                ->where('is_enabled', true)
                ->first();

            if ($integration) {
                $real = new \App\Integrations\Providers\WhatsApp\WhatsAppCloudProvider($integration);
                if ($real->isConfigured()) {
                    return $real;
                }
            }

            return new \App\Integrations\Providers\WhatsApp\FakeWhatsAppProvider($integration);
        });

        // Hosting (WHM): real si la integración 'whm' está activa y configurada;
        // si no, el fake. Igual patrón que WhatsApp.
        $this->app->bind(HostingGateway::class, function ($app) {
            $integration = \App\Domain\Integrations\Models\Integration::where('key', 'whm')
                ->where('is_enabled', true)
                ->first();

            if ($integration) {
                $real = new \App\Integrations\Providers\Whm\WhmHostingProvider($integration);
                if ($real->isConfigured()) {
                    return $real;
                }
            }

            return new \App\Integrations\Providers\Whm\FakeWhmHostingProvider($integration);
        });

        // Dominios (Namecheap): real si la integración 'domains' está activa y
        // configurada; si no, el fake. La capa DomainGateway permite añadir
        // otro registrador más adelante sin tocar el resto de la app.
        $this->app->bind(DomainGateway::class, function ($app) {
            $integration = \App\Domain\Integrations\Models\Integration::where('key', 'domains')
                ->where('is_enabled', true)
                ->first();

            if ($integration) {
                $real = new \App\Integrations\Providers\Namecheap\NamecheapDomainProvider($integration);
                if ($real->isConfigured()) {
                    return $real;
                }
            }

            return new \App\Integrations\Providers\Namecheap\FakeNamecheapDomainProvider($integration);
        });

        // Pagos: resuelve el primer proveedor de pago (mercadopago/openpay/
        // paypal) que esté activo y configurado; si ninguno lo está, cae al
        // fake del primero activo, y si no hay ninguno activo, a un fake por
        // defecto. Permite tener varios instalados y elegir por credenciales.
        $this->app->bind(PaymentGateway::class, function ($app) {
            $enabled = \App\Domain\Integrations\Models\Integration::whereIn('key', array_keys($this->paymentProviders))
                ->where('is_enabled', true)
                ->get();

            // 1) Primer proveedor activo y configurado -> real.
            foreach ($enabled as $integration) {
                [$realClass] = $this->paymentProviders[$integration->key];
                $real = new $realClass($integration);
                if ($real->isConfigured()) {
                    return $real;
                }
            }

            // 2) Primer proveedor activo pero sin configurar -> su fake.
            if ($enabled->isNotEmpty()) {
                $first = $enabled->first();
                [, $fakeClass] = $this->paymentProviders[$first->key];

                return new $fakeClass($first);
            }

            // 3) Ninguno instalado -> fake de Mercado Pago por defecto.
            return new \App\Integrations\Providers\MercadoPago\FakeMercadoPagoProvider();
        });

        // Notificaciones internas (Slack): real si la integración 'slack' está
        // activa y configurada (webhook_url); si no, el fake.
        $this->app->bind(NotificationGateway::class, function ($app) {
            $integration = \App\Domain\Integrations\Models\Integration::where('key', 'slack')
                ->where('is_enabled', true)
                ->first();

            if ($integration) {
                $real = new \App\Integrations\Providers\Slack\SlackProvider($integration);
                if ($real->isConfigured()) {
                    return $real;
                }
            }

            return new \App\Integrations\Providers\Slack\FakeSlackProvider($integration);
        });

        // El MailGateway se decora con LoggingMailGateway para registrar cada
        // envío en email_logs. El gateway interno se elige en runtime: si hay
        // configuración SMTP guardada en settings se envía de verdad (SMTP);
        // si no, se usa el fake (queda en el log). Registrar el correo es
        // transversal al proveedor.
        $this->app->bind(MailGateway::class, function ($app) {
            $settings = $app->make(\App\Integrations\Mail\SmtpSettings::class);

            $inner = $settings->isConfigured()
                ? $app->make(\App\Integrations\Mail\SmtpMailGateway::class)
                : $app->make(FakeMailGateway::class);

            return new LoggingMailGateway($inner);
        });

        // Registro central de integraciones con su catálogo de manifiestos.
        // Los providers concretos (real/fake) se enlazan en fases posteriores.
        $this->app->singleton(IntegrationRegistry::class, function () {
            $registry = new IntegrationRegistry();

            foreach ($this->catalog() as $manifest) {
                $registry->register($manifest);
            }

            // Handlers de webhooks entrantes por proveedor.
            $registry->registerWebhookHandler(
                'custom_api',
                \App\Integrations\Webhooks\Handlers\CustomApiWebhookHandler::class,
            );
            $registry->registerWebhookHandler(
                'mercadopago',
                \App\Integrations\Webhooks\Handlers\MercadoPagoWebhookHandler::class,
            );
            $registry->registerWebhookHandler(
                'openpay',
                \App\Integrations\Webhooks\Handlers\OpenpayWebhookHandler::class,
            );
            $registry->registerWebhookHandler(
                'paypal',
                \App\Integrations\Webhooks\Handlers\PayPalWebhookHandler::class,
            );

            return $registry;
        });
    }

    /**
     * Catálogo inicial de integraciones disponibles. Solo metadatos: las
     * conexiones reales no se implementan todavía.
     *
     * @return list<IntegrationManifest>
     */
    private function catalog(): array
    {
        return [
            new IntegrationManifest(
                key: 'monday',
                name: 'Monday.com',
                capability: IntegrationCapability::Tickets,
                description: 'Gestión de tickets internos.',
                configFields: ['board_id'],
                secretFields: ['api_token'],
                events: ['TicketCreated', 'TicketStatusChanged', 'TicketClosed'],
                supportsWebhooks: true,
                providerClass: \App\Integrations\Providers\Monday\MondayTicketProvider::class,
                fakeProviderClass: \App\Integrations\Providers\Monday\FakeMondayTicketProvider::class,
            ),
            new IntegrationManifest(
                key: 'whatsapp',
                name: 'WhatsApp',
                capability: IntegrationCapability::Notifications,
                description: 'Notificaciones por WhatsApp (Cloud API de Meta).',
                configFields: ['phone_number_id', 'api_version', 'template_language', 'template_renewal', 'template_ticket_reply', 'template_maintenance'],
                secretFields: ['access_token'],
                events: ['ServiceExpiring', 'DomainExpiring', 'TicketStatusChanged'],
                providerClass: \App\Integrations\Providers\WhatsApp\WhatsAppCloudProvider::class,
                fakeProviderClass: \App\Integrations\Providers\WhatsApp\FakeWhatsAppProvider::class,
            ),
            new IntegrationManifest(
                key: 'smtp',
                name: 'SMTP',
                capability: IntegrationCapability::Mail,
                description: 'Envío de correo electrónico.',
                configFields: ['host', 'port', 'encryption', 'from_address', 'from_name', 'username'],
                secretFields: ['password'],
                // SMTP reutiliza el gateway existente (SmtpSettings + email_logs);
                // el provider es seguro en dev (Mailpit) y en pruebas.
                providerClass: \App\Integrations\Providers\Smtp\SmtpMailProvider::class,
                fakeProviderClass: \App\Integrations\Providers\Smtp\SmtpMailProvider::class,
            ),
            new IntegrationManifest(
                key: 'whm',
                name: 'WHM / cPanel',
                capability: IntegrationCapability::Hosting,
                description: 'Gestión de cuentas de hosting (WHM API 1). La conexión se configura en cada Servidor (Sistema → Servidores), no aquí, para permitir varios servidores WHM.',
                providerClass: \App\Integrations\Providers\Whm\WhmHostingProvider::class,
                fakeProviderClass: \App\Integrations\Providers\Whm\FakeWhmHostingProvider::class,
            ),
            new IntegrationManifest(
                key: 'domains',
                name: 'Dominios (Namecheap)',
                capability: IntegrationCapability::Domains,
                description: 'Registro, renovación y consulta de dominios (API de Namecheap).',
                configFields: ['api_user', 'username', 'client_ip', 'sandbox'],
                secretFields: ['api_key'],
                providerClass: \App\Integrations\Providers\Namecheap\NamecheapDomainProvider::class,
                fakeProviderClass: \App\Integrations\Providers\Namecheap\FakeNamecheapDomainProvider::class,
            ),
            new IntegrationManifest(
                key: 'mercadopago',
                name: 'Mercado Pago',
                capability: IntegrationCapability::Payments,
                description: 'Cobros en línea con Mercado Pago (Checkout Pro).',
                configFields: ['currency'],
                secretFields: ['access_token', 'webhook_secret'],
                events: ['PaymentApproved', 'PaymentRejected'],
                supportsWebhooks: true,
                providerClass: \App\Integrations\Providers\MercadoPago\MercadoPagoProvider::class,
                fakeProviderClass: \App\Integrations\Providers\MercadoPago\FakeMercadoPagoProvider::class,
            ),
            new IntegrationManifest(
                key: 'openpay',
                name: 'Openpay',
                capability: IntegrationCapability::Payments,
                description: 'Cobros con Openpay (referencia de pago).',
                configFields: ['merchant_id', 'currency', 'sandbox'],
                secretFields: ['private_key', 'webhook_secret'],
                events: ['PaymentApproved', 'PaymentRejected'],
                supportsWebhooks: true,
                providerClass: \App\Integrations\Providers\Openpay\OpenpayProvider::class,
                fakeProviderClass: \App\Integrations\Providers\Openpay\FakeOpenpayProvider::class,
            ),
            new IntegrationManifest(
                key: 'paypal',
                name: 'PayPal',
                capability: IntegrationCapability::Payments,
                description: 'Cobros con PayPal (Orders API).',
                configFields: ['client_id', 'currency', 'sandbox'],
                secretFields: ['client_secret', 'webhook_id'],
                events: ['PaymentApproved', 'PaymentRejected'],
                supportsWebhooks: true,
                providerClass: \App\Integrations\Providers\PayPal\PayPalProvider::class,
                fakeProviderClass: \App\Integrations\Providers\PayPal\FakePayPalProvider::class,
            ),
            new IntegrationManifest(
                key: 'slack',
                name: 'Slack',
                capability: IntegrationCapability::Notifications,
                description: 'Notificaciones internas al equipo (Incoming Webhook).',
                configFields: ['channel', 'username', 'icon_emoji'],
                secretFields: ['webhook_url'],
                providerClass: \App\Integrations\Providers\Slack\SlackProvider::class,
                fakeProviderClass: \App\Integrations\Providers\Slack\FakeSlackProvider::class,
            ),
            new IntegrationManifest(
                key: 'custom_api',
                name: 'API personalizada',
                capability: IntegrationCapability::Rest,
                description: 'Conectar cualquier API REST compatible.',
                configFields: ['base_url', 'endpoint', 'method', 'auth_type', 'timeout', 'retries'],
                secretFields: ['auth_token', 'webhook_secret'],
                supportsWebhooks: true,
                providerClass: \App\Integrations\Providers\CustomApi\CustomApiProvider::class,
                fakeProviderClass: FakeGenericProvider::class,
            ),
        ];
    }
}
