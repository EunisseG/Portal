<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\MailGateway;
use App\Livewire\Admin\Clients\ClientShow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ResendPasswordLinkTest extends TestCase
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

    private function clientWith(?string $secondary = null): Client
    {
        $user = User::factory()->create([
            'email' => 'cliente@wortev.test',
            'secondary_email' => $secondary,
        ]);
        $user->assignRole(RoleName::Client->value);

        return Client::factory()->create(['user_id' => $user->id]);
    }

    private function expectMailTo(string $expected): void
    {
        $mock = Mockery::mock(MailGateway::class);
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $to) => $to === $expected)
            ->andReturn(GatewayResult::ok());
        $this->app->instance(MailGateway::class, $mock);
    }

    public function test_resend_defaults_to_primary_email(): void
    {
        $client = $this->clientWith();
        $this->expectMailTo('cliente@wortev.test');

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->call('resendPasswordLink')
            ->assertHasNoErrors();
    }

    public function test_admin_can_send_to_secondary_email(): void
    {
        $client = $this->clientWith('respaldo@gmail.com');
        $this->expectMailTo('respaldo@gmail.com');

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->call('startResend')
            ->set('resendTarget', 'secondary')
            ->call('resendPasswordLink')
            ->assertHasNoErrors();
    }

    public function test_secondary_choice_ignored_when_client_has_no_secondary(): void
    {
        // Si eligen secundario pero no existe, se envía al principal (seguro).
        $client = $this->clientWith();
        $this->expectMailTo('cliente@wortev.test');

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->set('resendTarget', 'secondary')
            ->call('resendPasswordLink')
            ->assertHasNoErrors();
    }
}
