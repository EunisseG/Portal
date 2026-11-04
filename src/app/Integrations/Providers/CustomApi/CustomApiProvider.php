<?php

namespace App\Integrations\Providers\CustomApi;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Actions\ExecuteRestRequest;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\RestApiGateway;
use App\Integrations\DTO\RestRequestSpec;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Support\SecretString;

/**
 * Provider de API personalizada. Arma un RestRequestSpec a partir de la config
 * guardada de la integración (base URL, endpoint, método, auth) y delega la
 * ejecución HTTP controlada a ExecuteRestRequest. No ejecuta código arbitrario.
 */
class CustomApiProvider implements RestApiGateway
{
    public function __construct(
        protected readonly Integration $integration,
        protected readonly ExecuteRestRequest $executor,
    ) {}

    public function key(): string
    {
        return $this->integration->key;
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Rest;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->integration->config['base_url'] ?? null);
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Falta la URL base.');
        }

        return $this->execute($this->buildSpec());
    }

    public function execute(RestRequestSpec $spec): GatewayResult
    {
        return $this->executor->execute($spec);
    }

    /** Construye el spec desde la configuración guardada de la integración. */
    public function buildSpec(): RestRequestSpec
    {
        $config = $this->integration->config ?? [];

        $authType = $config['auth_type'] ?? RestRequestSpec::AUTH_NONE;
        $auth = $this->buildAuth($authType);

        return new RestRequestSpec(
            method: $config['method'] ?? 'GET',
            baseUrl: (string) ($config['base_url'] ?? ''),
            endpoint: (string) ($config['endpoint'] ?? ''),
            headers: (array) ($config['headers'] ?? []),
            query: (array) ($config['query'] ?? []),
            body: (array) ($config['body'] ?? []),
            authType: $authType,
            auth: $auth,
            timeout: (int) ($config['timeout'] ?? 15),
            retries: (int) ($config['retries'] ?? 0),
            responseFormat: $config['response_format'] ?? 'json',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAuth(string $authType): array
    {
        $token = $this->secret('auth_token');

        return match ($authType) {
            RestRequestSpec::AUTH_BEARER => ['token' => $token],
            RestRequestSpec::AUTH_OAUTH2 => ['access_token' => $token],
            RestRequestSpec::AUTH_API_KEY => [
                'header' => $this->integration->config['api_key_header'] ?? 'X-API-Key',
                'key' => $token,
            ],
            RestRequestSpec::AUTH_BASIC => [
                'username' => $this->integration->config['username'] ?? '',
                'password' => $token,
            ],
            default => [],
        };
    }

    private function secret(string $key): ?SecretString
    {
        $secret = $this->integration->secrets()->where('key', $key)->first();

        return $secret ? new SecretString($secret->reveal()) : null;
    }
}
