<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\MailGateway;
use App\Livewire\Admin\Clients\ClientForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class SecondaryEmailRecoveryTest extends TestCase
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

    public function test_profile_update_saves_secondary_email(): void
    {
        $user = User::factory()->create(['email' => 'principal@wortev.test']);

        $this->actingAs($user)
            ->put('/user/profile-information', [
                'name' => $user->name,
                'email' => 'principal@wortev.test',
                'secondary_email' => 'respaldo@gmail.com',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('respaldo@gmail.com', $user->fresh()->secondary_email);
    }

    public function test_secondary_email_must_differ_from_primary(): void
    {
        $user = User::factory()->create(['email' => 'principal@wortev.test']);

        $this->actingAs($user)
            ->put('/user/profile-information', [
                'name' => $user->name,
                'email' => 'principal@wortev.test',
                'secondary_email' => 'principal@wortev.test',
            ])
            ->assertSessionHasErrors('secondary_email', null, 'updateProfileInformation');

        $this->assertNull($user->fresh()->secondary_email);
    }

    public function test_recovery_link_sent_to_secondary_email(): void
    {
        $user = User::factory()->create([
            'email' => 'principal@wortev.test',
            'secondary_email' => 'respaldo@gmail.com',
        ]);

        $mock = Mockery::mock(MailGateway::class);
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $to) => $to === 'respaldo@gmail.com')
            ->andReturn(GatewayResult::ok());
        $this->app->instance(MailGateway::class, $mock);

        $this->post('/recuperar-contrasena', ['email' => 'respaldo@gmail.com'])
            ->assertSessionHas('status');
    }

    public function test_recovery_link_generic_response_for_unknown_email(): void
    {
        // No debe fallar ni revelar si el correo existe.
        $mock = Mockery::mock(MailGateway::class);
        $mock->shouldReceive('send')->never();
        $this->app->instance(MailGateway::class, $mock);

        $this->post('/recuperar-contrasena', ['email' => 'desconocido@nope.test'])
            ->assertSessionHas('status');
    }

    public function test_admin_can_set_client_secondary_email(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ClientForm::class)
            ->set('name', 'Cliente Dos Correos')
            ->set('email', 'cliente@wortev.test')
            ->set('secondary_email', 'alterno@wortev.test')
            ->call('save');

        $user = User::where('email', 'cliente@wortev.test')->firstOrFail();
        $this->assertSame('alterno@wortev.test', $user->secondary_email);
    }
}
