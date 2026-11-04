<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientMemberService;
use App\Livewire\Admin\Clients\ClientShow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientShowTest extends TestCase
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

    /** Titular (user + client + rol client). */
    private function clientWithOwner(array $clientAttrs = [], array $userAttrs = []): Client
    {
        $owner = User::factory()->create($userAttrs);
        $owner->assignRole(RoleName::Client->value);

        return Client::factory()->create(array_merge(['user_id' => $owner->id], $clientAttrs));
    }

    public function test_datos_tab_loads_structured_client_fields_into_the_form(): void
    {
        $client = $this->clientWithOwner([
            'company' => 'ACME S.A.',
            'address_line1' => 'Av. Reforma 100',
            'city' => 'CDMX',
            'state' => 'Ciudad de México',
            'postal_code' => '06600',
            'country' => 'MX',
        ], [
            'name' => 'Ana',
            'last_name' => 'López',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->assertOk()
            ->assertSet('name', 'Ana')
            ->assertSet('last_name', 'López')
            ->assertSet('company', 'ACME S.A.')
            ->assertSet('address_line1', 'Av. Reforma 100')
            ->assertSet('city', 'CDMX')
            ->assertSet('state', 'Ciudad de México')
            ->assertSet('postal_code', '06600')
            ->assertSet('country', 'MX');
    }

    public function test_admin_can_edit_client_inline_from_datos_tab(): void
    {
        $client = $this->clientWithOwner();

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->set('name', 'Nuevo Nombre')
            ->set('last_name', 'Apellido Nuevo')
            ->set('company', 'Empresa Editada')
            ->set('address_line1', 'Calle Nueva 500')
            ->set('city', 'Monterrey')
            ->set('state', 'Nuevo León')
            ->set('postal_code', '64000')
            ->set('country', 'MX')
            ->set('billing_contact', 'owner')
            ->call('save')
            ->assertHasNoErrors();

        $client->refresh();
        $this->assertSame('Nuevo Nombre', $client->user->name);
        $this->assertSame('Apellido Nuevo', $client->user->last_name);
        $this->assertSame('Empresa Editada', $client->company);
        $this->assertSame('Calle Nueva 500', $client->address_line1);
        $this->assertSame('Monterrey', $client->city);
        $this->assertSame('Nuevo León', $client->state);
        $this->assertSame('64000', $client->postal_code);
        $this->assertSame('MX', $client->country);
    }

    public function test_inline_edit_validates_email(): void
    {
        $client = $this->clientWithOwner();
        $other = $this->clientWithOwner();

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->set('email', $other->user->email) // email ya en uso
            ->call('save')
            ->assertHasErrors('email');
    }

    public function test_datos_tab_shows_two_factor_state_for_owner(): void
    {
        // 2FA inactivo.
        $clientOff = $this->clientWithOwner();

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $clientOff])
            ->assertSeeInOrder(['Autenticación en dos pasos (2FA)', 'Inactivo']);

        // 2FA activo: secret + confirmado.
        $clientOn = $this->clientWithOwner();
        $clientOn->user->forceFill([
            'two_factor_secret' => encrypt('SECRETO123'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $clientOn->fresh('user')])
            ->assertSee('Autenticación en dos pasos (2FA)')
            ->assertSee('Activo');
    }

    public function test_datos_tab_shows_sub_accounts_with_their_two_factor_state(): void
    {
        $client = $this->clientWithOwner();

        $member = app(ClientMemberService::class)->create($client, [
            'name' => 'Colaborador Uno',
            'email' => 'colab@wortev.test',
        ]);
        $member->forceFill([
            'two_factor_secret' => encrypt('SECRETO456'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client->fresh()])
            ->assertSee('Sub-cuentas')
            ->assertSee('Colaborador Uno')
            ->assertSee('colab@wortev.test');
    }

    public function test_datos_tab_shows_message_when_no_sub_accounts(): void
    {
        $client = $this->clientWithOwner();

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->assertSee('Este cliente no tiene sub-cuentas.');
    }

    public function test_admin_can_disable_two_factor_of_owner(): void
    {
        $client = $this->clientWithOwner();
        $client->user->forceFill([
            'two_factor_secret' => encrypt('SECRETO123'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->assertTrue($client->user->fresh()->hasEnabledTwoFactorAuthentication());

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client->fresh()])
            ->call('disableTwoFactor', $client->user->id);

        $this->assertFalse($client->user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->assertNull($client->user->fresh()->two_factor_secret);
    }

    public function test_admin_can_disable_two_factor_of_sub_user(): void
    {
        $client = $this->clientWithOwner();
        $member = app(ClientMemberService::class)->create($client, [
            'name' => 'Colab', 'email' => 'colab@wortev.test',
        ]);
        $member->forceFill([
            'two_factor_secret' => encrypt('SECRETO456'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client->fresh()])
            ->call('disableTwoFactor', $member->id);

        $this->assertFalse($member->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_admin_cannot_disable_two_factor_of_user_outside_the_client(): void
    {
        $client = $this->clientWithOwner();

        // Usuario de OTRO cliente, ajeno a esta cuenta.
        $otherClient = $this->clientWithOwner();
        $otherClient->user->forceFill([
            'two_factor_secret' => encrypt('AJENO789'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client->fresh()])
            ->call('disableTwoFactor', $otherClient->user->id)
            ->assertForbidden();

        // El 2FA del usuario ajeno sigue intacto.
        $this->assertTrue($otherClient->user->fresh()->hasEnabledTwoFactorAuthentication());
    }
}
