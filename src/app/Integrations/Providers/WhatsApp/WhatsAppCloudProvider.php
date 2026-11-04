<?php

namespace App\Integrations\Providers\WhatsApp;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Contracts\WhatsAppGateway;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor REAL de WhatsApp vía la Cloud API de Meta. Envía mensajes de
 * plantilla (los únicos permitidos para mensajes iniciados por el negocio).
 *
 * Credenciales (en la integración 'whatsapp'):
 *   - config: phone_number_id, api_version (opcional), template_language (opcional)
 *   - secret: access_token (cifrado)
 *
 * "Listo para conectar": el código de envío es real; sin credenciales
 * configuradas devuelve fail sin llamar a la API.
 */
class WhatsAppCloudProvider implements WhatsAppGateway, IntegrationProvider
{
    public function __construct(protected readonly Integration $integration) {}

    public function key(): string
    {
        return 'whatsapp';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Notifications;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->phoneNumberId()) && $this->accessToken() !== null;
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('WhatsApp no configurado: falta phone_number_id o access_token.');
        }

        // No enviamos un mensaje real al probar; solo confirmamos configuración.
        return GatewayResult::ok('Credenciales de WhatsApp presentes.');
    }

    /**
     * Envía un mensaje de plantilla. $template es el nombre de la plantilla
     * aprobada en Meta; $params se mapean como variables del cuerpo.
     *
     * @param  array<string, mixed>  $params
     */
    public function sendMessage(string $to, string $template, array $params = []): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('WhatsApp no configurado.');
        }

        $version = $this->integration->config['api_version'] ?? 'v20.0';
        $language = $this->integration->config['template_language'] ?? 'es_MX';
        $url = "https://graph.facebook.com/{$version}/{$this->phoneNumberId()}/messages";

        // Las variables de la plantilla van como componente "body" en orden.
        $components = [];
        if (! empty($params)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    fn ($value) => ['type' => 'text', 'text' => (string) $value],
                    array_values($params),
                ),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizeNumber($to),
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ];

        try {
            $response = Http::withToken($this->accessToken())
                ->timeout(15)
                ->retry(2, 200)
                ->post($url, $payload);

            if ($response->successful()) {
                return GatewayResult::ok('Mensaje de WhatsApp enviado.', [
                    'http_status' => $response->status(),
                    'to' => $to,
                ]);
            }

            return GatewayResult::fail('WhatsApp HTTP '.$response->status(), [
                'http_status' => $response->status(),
                'to' => $to,
            ]);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppCloudProvider] send failed', ['to' => $to, 'error' => $e->getMessage()]);

            return GatewayResult::fail('No se pudo enviar WhatsApp: '.$e->getMessage(), ['to' => $to]);
        }
    }

    private function phoneNumberId(): ?string
    {
        return $this->integration->config['phone_number_id'] ?? null;
    }

    private function accessToken(): ?string
    {
        $secret = $this->integration->secrets()->where('key', 'access_token')->first();

        return $secret?->reveal();
    }

    /** Normaliza el número a solo dígitos (formato que espera la Cloud API). */
    private function normalizeNumber(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? $number;
    }
}
