<?php

namespace Tests\Feature\Client;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientMemberService;
use App\Domain\Notifications\Models\EmailLog;
use App\Livewire\Client\Account\AccountContacts;
use App\Livewire\Client\Account\EmailsReceived;
use App\Livewire\Client\Account\ProfileData;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Crea un titular (user + client + rol client). */
    private function owner(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_owner_can_update_profile_and_address(): void
    {
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(ProfileData::class)
            ->set('name', 'Ana')
            ->set('last_name', 'López')
            ->set('company', 'Mi Empresa SA')
            ->set('address_line1', 'Av. Reforma 100')
            ->set('city', 'CDMX')
            ->set('state', 'Ciudad de México')
            ->set('postal_code', '06600')
            ->set('country', 'MX')
            ->call('save')
            ->assertHasNoErrors();

        $owner->refresh();
        $this->assertSame('Ana', $owner->name);
        $this->assertSame('López', $owner->last_name);

        $client = $owner->client->fresh();
        $this->assertSame('Mi Empresa SA', $client->company);
        $this->assertSame('Av. Reforma 100', $client->address_line1);
        $this->assertSame('CDMX', $client->city);
        $this->assertSame('06600', $client->postal_code);
        $this->assertSame('MX', $client->country);
    }

    public function test_sub_user_cannot_change_company_or_address(): void
    {
        $owner = $this->owner();
        $owner->client->update(['company' => 'Original SA', 'address_line1' => 'Calle 1']);

        $member = app(ClientMemberService::class)->create($owner->client, [
            'name' => 'Colab', 'email' => 'colab@wortev.test',
        ]);

        Livewire::actingAs($member->refresh())
            ->test(ProfileData::class)
            ->set('name', 'Colaborador')
            ->set('company', 'Hackeada SA')
            ->set('address_line1', 'Calle Falsa 123')
            ->call('save')
            ->assertHasNoErrors();

        // Sus datos de cuenta sí cambian.
        $this->assertSame('Colaborador', $member->fresh()->name);

        // Los datos del titular/cliente NO se tocan.
        $client = $owner->client->fresh();
        $this->assertSame('Original SA', $client->company);
        $this->assertSame('Calle 1', $client->address_line1);
    }

    public function test_sub_user_cannot_access_contacts_section(): void
    {
        $owner = $this->owner();
        $member = app(ClientMemberService::class)->create($owner->client, [
            'name' => 'Colab', 'email' => 'colab@wortev.test',
        ]);

        Livewire::actingAs($member->refresh())
            ->test(AccountContacts::class)
            ->assertForbidden();
    }

    public function test_owner_can_manage_sub_user_from_contacts_section(): void
    {
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(AccountContacts::class)
            ->call('startCreate')
            ->set('name', 'Colaborador')
            ->set('email', 'colab@wortev.test')
            ->set('permissions', ['client.invoices.view'])
            ->call('save')
            ->assertHasNoErrors();

        $member = User::where('email', 'colab@wortev.test')->firstOrFail();
        $this->assertSame($owner->client->id, $member->parent_client_id);
    }

    public function test_emails_received_is_isolated_to_the_user(): void
    {
        $owner = $this->owner();
        $owner->update(['secondary_email' => 'ana.alt@wortev.test']);

        $other = $this->owner();

        // Correos del titular (principal y secundario).
        EmailLog::create([
            'recipient' => $owner->email, 'subject' => 'Factura del titular',
            'body' => '<p>hola</p>', 'success' => true, 'sent_at' => now(),
        ]);
        EmailLog::create([
            'recipient' => 'ana.alt@wortev.test', 'subject' => 'Aviso secundario',
            'body' => '<p>hola</p>', 'success' => true, 'sent_at' => now(),
        ]);
        // Correo de otro usuario: no debe verse.
        EmailLog::create([
            'recipient' => $other->email, 'subject' => 'Correo ajeno',
            'body' => '<p>hola</p>', 'success' => true, 'sent_at' => now(),
        ]);

        Livewire::actingAs($owner)
            ->test(EmailsReceived::class)
            ->assertOk()
            ->assertSee('Factura del titular')
            ->assertSee('Aviso secundario')
            ->assertDontSee('Correo ajeno');
    }

    public function test_cannot_view_another_users_email_detail(): void
    {
        $owner = $this->owner();
        $other = $this->owner();

        $alien = EmailLog::create([
            'recipient' => $other->email, 'subject' => 'Correo ajeno',
            'body' => '<p>secreto</p>', 'success' => true, 'sent_at' => now(),
        ]);

        Livewire::actingAs($owner)
            ->test(EmailsReceived::class)
            ->call('view', $alien->id)
            ->assertSet('viewingId', null)
            ->assertDontSee('secreto');
    }

    public function test_profile_route_redirects_clients_to_account(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('profile.edit'))
            ->assertRedirect(route('client.account.profile'));
    }
}
