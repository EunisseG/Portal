<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Services\ClientService;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\MailGateway;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AccountSetupLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creating_a_client_sends_a_password_setup_link_email(): void
    {
        $mock = Mockery::mock(MailGateway::class);
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function (string $to, string $subject, string $body) {
                return $to === 'nuevo@wortev.test'
                    && str_contains($subject, 'Configura el acceso')
                    // El correo lleva un enlace a la ruta de reset (con token),
                    // nunca una contraseña en claro.
                    && str_contains($body, '/reset-password/');
            })
            ->andReturn(GatewayResult::ok());
        $this->app->instance(MailGateway::class, $mock);

        app(ClientService::class)->create([
            'name' => 'Cliente Nuevo',
            'email' => 'nuevo@wortev.test',
            'company' => 'ACME',
        ]);

        // El usuario se creó con rol client.
        $user = User::where('email', 'nuevo@wortev.test')->firstOrFail();
        $this->assertTrue($user->hasRole(RoleName::Client->value));
    }

    public function test_setup_email_does_not_contain_a_plaintext_password(): void
    {
        $captured = null;
        $mock = Mockery::mock(MailGateway::class);
        $mock->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (string $to, string $subject, string $body) use (&$captured) {
                $captured = $body;

                return GatewayResult::ok();
            });
        $this->app->instance(MailGateway::class, $mock);

        app(ClientService::class)->create([
            'name' => 'Otro Cliente',
            'email' => 'otro@wortev.test',
        ]);

        $this->assertNotNull($captured);
        // El cuerpo incluye el enlace y no expone una contraseña.
        $this->assertStringContainsString('reset-password', $captured);
        $this->assertStringNotContainsStringIgnoringCase('contraseña:', $captured);
    }
}
