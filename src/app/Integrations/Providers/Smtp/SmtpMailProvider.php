<?php

namespace App\Integrations\Providers\Smtp;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Mail\SmtpMailGateway;
use App\Integrations\Mail\SmtpSettings;
use App\Integrations\Registry\IntegrationCapability;

/**
 * Reencuadra el SMTP existente como una integración del catálogo (capability
 * mail). Reutiliza SmtpSettings (tabla settings) y SmtpMailGateway sin cambiar
 * el envío de correo del portal ni el registro en email_logs.
 *
 * La configuración detallada (host, credenciales, remitente) sigue en la
 * pantalla dedicada de "Configuración de correo".
 */
class SmtpMailProvider implements IntegrationProvider
{
    public function __construct(
        protected readonly Integration $integration,
        protected readonly SmtpSettings $settings,
        protected readonly SmtpMailGateway $gateway,
    ) {}

    public function key(): string
    {
        return 'smtp';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Mail;
    }

    public function isConfigured(): bool
    {
        return $this->settings->isConfigured();
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('SMTP no configurado: falta el host.');
        }

        // Envía un correo de prueba real por SMTP a la dirección remitente.
        return $this->gateway->sendTestEmail($this->settings->fromAddress());
    }
}
