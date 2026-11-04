<?php

namespace Tests\Feature\Integrations;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationSecret;
use App\Integrations\Registry\IntegrationCapability;
use App\Integrations\Support\RedactsSecrets;
use App\Integrations\Support\SecretString;
use App\Livewire\Admin\Integrations\IntegrationConfigure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationSecretsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    private function monday(): Integration
    {
        return Integration::create([
            'key' => 'monday',
            'name' => 'Monday.com',
            'capability' => IntegrationCapability::Tickets->value,
        ]);
    }

    public function test_secret_string_never_exposes_the_value(): void
    {
        $secret = new SecretString('token-super-secreto-9999');

        $this->assertSame('••••••9999', (string) $secret);
        $this->assertSame('••••••9999', $secret->masked());
        $this->assertSame('••••••9999', $secret->jsonSerialize());
        $this->assertStringNotContainsString('super-secreto', json_encode($secret));
        // Solo reveal() expone el valor real.
        $this->assertSame('token-super-secreto-9999', $secret->reveal());
    }

    public function test_redacts_secrets_masks_sensitive_keys(): void
    {
        $redactor = new class {
            use RedactsSecrets;

            public function run(array $data): array
            {
                return $this->redact($data);
            }
        };

        $out = $redactor->run([
            'board_id' => 123,
            'api_token' => 'abc',
            'nested' => ['authorization' => 'Bearer x', 'ok' => 'visible'],
        ]);

        $this->assertSame(123, $out['board_id']);
        $this->assertSame('[REDACTED]', $out['api_token']);
        $this->assertSame('[REDACTED]', $out['nested']['authorization']);
        $this->assertSame('visible', $out['nested']['ok']);
    }

    public function test_configure_saves_config_and_encrypted_secret(): void
    {
        $integration = $this->monday();

        Livewire::actingAs($this->admin())
            ->test(IntegrationConfigure::class, ['integration' => $integration])
            ->set('config.board_id', '987654')
            ->set('secrets.api_token', 'monday-token-1234')
            ->call('save');

        $integration->refresh();
        $this->assertSame('987654', $integration->config['board_id']);

        $secret = IntegrationSecret::where('integration_id', $integration->id)->where('key', 'api_token')->firstOrFail();
        // Guardado cifrado (el valor crudo no es el texto plano) pero descifrable.
        $this->assertNotSame('monday-token-1234', $secret->getRawOriginal('value'));
        $this->assertSame('monday-token-1234', $secret->reveal());
        $this->assertSame('••••••1234', $secret->masked());
    }

    public function test_empty_secret_field_keeps_existing_value(): void
    {
        $integration = $this->monday();
        IntegrationSecret::putFor($integration, 'api_token', 'valor-original');

        Livewire::actingAs($this->admin())
            ->test(IntegrationConfigure::class, ['integration' => $integration])
            ->set('config.board_id', '111')
            ->set('secrets.api_token', '') // vacío = conservar
            ->call('save');

        $secret = IntegrationSecret::where('integration_id', $integration->id)->where('key', 'api_token')->firstOrFail();
        $this->assertSame('valor-original', $secret->reveal());
    }

    public function test_secret_is_not_prefilled_in_the_component(): void
    {
        $integration = $this->monday();
        IntegrationSecret::putFor($integration, 'api_token', 'no-debe-aparecer');

        Livewire::actingAs($this->admin())
            ->test(IntegrationConfigure::class, ['integration' => $integration])
            // El campo del secreto arranca vacío (nunca precargado con el valor real).
            ->assertSet('secrets.api_token', '')
            // El valor real no aparece en el HTML renderizado.
            ->assertDontSee('no-debe-aparecer');
    }
}
