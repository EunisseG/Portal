<?php

namespace App\Integrations\Actions;

use App\Integrations\Contracts\GatewayResult;
use App\Integrations\DTO\RestRequestSpec;
use App\Integrations\Exceptions\RestRequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Ejecuta una petición HTTP REST controlada (API personalizada). No ejecuta
 * código arbitrario: solo realiza la solicitud descrita por el spec, aplicando
 * salvaguardas de seguridad (solo https, bloqueo de destinos internos/SSRF),
 * timeout y reintentos. Devuelve un GatewayResult uniforme.
 */
class ExecuteRestRequest
{
    /** ¿Permitir hosts internos? Solo en entorno de pruebas (Http::fake). */
    private bool $allowInternalHosts = false;

    public function allowInternalHostsForTesting(): self
    {
        $this->allowInternalHosts = true;

        return $this;
    }

    public function execute(RestRequestSpec $spec): GatewayResult
    {
        $url = $spec->url();

        try {
            $this->guardUrl($url);
        } catch (RestRequestException $e) {
            return GatewayResult::fail($e->getMessage(), ['url' => $url]);
        }

        $request = $this->applyAuth(
            Http::timeout($spec->timeout)->retry(max(1, $spec->retries + 1), 100)->withHeaders($spec->headers),
            $spec,
        );

        $start = microtime(true);

        try {
            $response = $request->send($spec->methodUpper(), $url, $this->options($spec));

            $data = [
                'http_status' => $response->status(),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'body' => $spec->responseFormat === 'json' ? $response->json() : $response->body(),
            ];

            if ($response->successful()) {
                return GatewayResult::ok('Petición ejecutada.', $data);
            }

            return GatewayResult::fail('HTTP '.$response->status(), $data);
        } catch (\Throwable $e) {
            return GatewayResult::fail('Error de conexión: '.$e->getMessage(), [
                'url' => $url,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function options(RestRequestSpec $spec): array
    {
        $options = [];

        if (! empty($spec->query)) {
            $options['query'] = $spec->query;
        }

        if (in_array($spec->methodUpper(), ['POST', 'PUT', 'PATCH'], true) && ! empty($spec->body)) {
            $options['json'] = $spec->body;
        }

        return $options;
    }

    private function applyAuth(PendingRequest $request, RestRequestSpec $spec): PendingRequest
    {
        return match ($spec->authType) {
            RestRequestSpec::AUTH_BEARER => $request->withToken(
                (string) $spec->authSecret('token')?->reveal()
            ),
            RestRequestSpec::AUTH_API_KEY => $request->withHeaders([
                ($spec->auth['header'] ?? 'X-API-Key') => (string) $spec->authSecret('key')?->reveal(),
            ]),
            RestRequestSpec::AUTH_BASIC => $request->withBasicAuth(
                (string) ($spec->auth['username'] ?? ''),
                (string) $spec->authSecret('password')?->reveal(),
            ),
            RestRequestSpec::AUTH_OAUTH2 => $request->withToken(
                (string) $spec->authSecret('access_token')?->reveal()
            ),
            default => $request,
        };
    }

    /** Salvaguardas: solo https y sin destinos internos (anti-SSRF). */
    private function guardUrl(string $url): void
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https') {
            throw new RestRequestException('Solo se permiten URLs https.');
        }

        $host = $parts['host'] ?? '';
        if ($host === '') {
            throw new RestRequestException('URL sin host válido.');
        }

        if ($this->allowInternalHosts) {
            return;
        }

        // Bloquear localhost y rangos privados/reservados (SSRF).
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RestRequestException('Destino no permitido (host local).');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPublic = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($isPublic === false) {
                throw new RestRequestException('Destino no permitido (IP privada o reservada).');
            }
        }
    }
}
