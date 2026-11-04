<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Integrations\Actions\ExecuteRestRequest;
use App\Integrations\DTO\RestRequestSpec;
use App\Integrations\Providers\CustomApi\CustomApiProvider;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Support\SecretString;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class CustomApiTest extends TestCase
{
    use RefreshDatabase;

    private function executor(): ExecuteRestRequest
    {
        return (new ExecuteRestRequest())->allowInternalHostsForTesting();
    }

    public function test_executes_a_get_request_and_returns_json(): void
    {
        Http::fake([
            'https://api.example.com/*' => Http::response(['ok' => true, 'items' => [1, 2]], 200),
        ]);

        $spec = new RestRequestSpec(method: 'GET', baseUrl: 'https://api.example.com', endpoint: '/items');
        $result = $this->executor()->execute($spec);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->data['http_status']);
        $this->assertSame([1, 2], $result->data['body']['items']);
    }

    public function test_sends_post_body_and_bearer_token(): void
    {
        Http::fake(['https://api.example.com/*' => Http::response(['created' => true], 201)]);

        $spec = new RestRequestSpec(
            method: 'POST',
            baseUrl: 'https://api.example.com',
            endpoint: '/things',
            body: ['name' => 'X'],
            authType: RestRequestSpec::AUTH_BEARER,
            auth: ['token' => new SecretString('token-abc')],
        );

        $result = $this->executor()->execute($spec);

        $this->assertTrue($result->success);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer token-abc')
                && $request['name'] === 'X';
        });
    }

    public function test_api_key_auth_sends_custom_header(): void
    {
        Http::fake(['https://api.example.com/*' => Http::response([], 200)]);

        $spec = new RestRequestSpec(
            method: 'GET',
            baseUrl: 'https://api.example.com',
            authType: RestRequestSpec::AUTH_API_KEY,
            auth: ['header' => 'X-Api-Key', 'key' => new SecretString('secret-key')],
        );

        $this->executor()->execute($spec);

        Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'secret-key'));
    }

    public function test_basic_auth_is_applied(): void
    {
        Http::fake(['https://api.example.com/*' => Http::response([], 200)]);

        $spec = new RestRequestSpec(
            method: 'GET',
            baseUrl: 'https://api.example.com',
            authType: RestRequestSpec::AUTH_BASIC,
            auth: ['username' => 'user', 'password' => new SecretString('pass')],
        );

        $this->executor()->execute($spec);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization'));
    }

    public function test_http_error_returns_failure_with_status(): void
    {
        Http::fake(['https://api.example.com/*' => Http::response(['error' => 'nope'], 422)]);

        $spec = new RestRequestSpec(method: 'GET', baseUrl: 'https://api.example.com', endpoint: '/x');
        $result = $this->executor()->execute($spec);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->data['http_status']);
    }

    public function test_non_https_url_is_rejected(): void
    {
        // Sin allowInternalHosts, y además http no permitido.
        $spec = new RestRequestSpec(method: 'GET', baseUrl: 'http://api.example.com', endpoint: '/x');
        $result = (new ExecuteRestRequest())->execute($spec);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('https', $result->message);
    }

    public function test_private_ip_is_blocked_as_ssrf(): void
    {
        $spec = new RestRequestSpec(method: 'GET', baseUrl: 'https://192.168.1.10', endpoint: '/x');
        $result = (new ExecuteRestRequest())->execute($spec);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('no permitido', $result->message);
    }

    public function test_invalid_method_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RestRequestSpec(method: 'CONNECT', baseUrl: 'https://api.example.com');
    }

    public function test_provider_builds_spec_from_config_and_secret(): void
    {
        Http::fake(['https://api.tercero.com/*' => Http::response(['pong' => true], 200)]);

        $integration = Integration::create([
            'key' => 'custom_api',
            'name' => 'API personalizada',
            'capability' => IntegrationCapability::Rest->value,
            'is_enabled' => true,
            'config' => [
                'base_url' => 'https://api.tercero.com',
                'endpoint' => '/ping',
                'method' => 'GET',
                'auth_type' => RestRequestSpec::AUTH_BEARER,
                'timeout' => 10,
                'retries' => 1,
            ],
        ]);
        IntegrationSecret::putFor($integration, 'auth_token', 'token-tercero');

        $provider = new CustomApiProvider($integration, $this->executor());
        $result = $provider->testConnection();

        $this->assertTrue($result->success);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-tercero'));
    }
}
