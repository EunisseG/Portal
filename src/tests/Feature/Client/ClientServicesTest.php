<?php

namespace Tests\Feature\Client;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientMemberService;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use App\Livewire\Client\Services\ServiceList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_client_sees_only_their_own_services(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();

        Service::factory()->create([
            'client_id' => $me->client->id,
            'name' => 'Mi Hosting Propio',
        ]);
        Service::factory()->create([
            'client_id' => $other->client->id,
            'name' => 'Hosting De Otro',
        ]);

        $this->actingAs($me)
            ->get('/client/servicios')
            ->assertOk()
            ->assertSee('Mi Hosting Propio')
            ->assertDontSee('Hosting De Otro');
    }

    public function test_client_sees_only_their_own_domains(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();

        Domain::factory()->create([
            'client_id' => $me->client->id,
            'name' => 'mipropio.com',
        ]);
        Domain::factory()->create([
            'client_id' => $other->client->id,
            'name' => 'ajeno.com',
        ]);

        $this->actingAs($me)
            ->get('/client/dominios')
            ->assertOk()
            ->assertSee('mipropio.com')
            ->assertDontSee('ajeno.com');
    }

    public function test_client_cannot_access_admin_services(): void
    {
        $this->actingAs($this->clientUser())
            ->get('/admin/servicios')
            ->assertForbidden();
    }

    public function test_services_page_has_both_tabs_and_can_switch(): void
    {
        $me = $this->clientUser();

        Service::factory()->create(['client_id' => $me->client->id, 'name' => 'Mi Servicio Uno']);
        Domain::factory()->create(['client_id' => $me->client->id, 'name' => 'midominio.com']);

        Livewire::actingAs($me)
            ->test(ServiceList::class)
            ->assertSet('tab', 'services')
            ->assertSee('Mis Servicios')
            ->assertSee('Mis Dominios')
            ->assertSee('Mi Servicio Uno')
            ->call('setTab', 'domains')
            ->assertSet('tab', 'domains')
            ->assertSee('midominio.com');
    }

    public function test_domains_entry_opens_domains_tab(): void
    {
        $me = $this->clientUser();
        Domain::factory()->create(['client_id' => $me->client->id, 'name' => 'entrada.com']);

        Livewire::actingAs($me)
            ->test(\App\Livewire\Client\Domains\DomainList::class)
            ->assertSet('tab', 'domains')
            ->assertSee('entrada.com');
    }

    public function test_sub_user_with_only_services_permission_sees_single_tab(): void
    {
        $owner = $this->clientUser();
        $member = app(ClientMemberService::class)->create($owner->client, [
            'name' => 'Colab',
            'email' => 'colab@wortev.test',
            'permissions' => ['client.services.view'], // sin dominios
        ]);

        // Puede entrar a la página de servicios (tiene al menos un permiso).
        Livewire::actingAs($member->refresh())
            ->test(ServiceList::class)
            ->assertOk()
            ->assertSet('tab', 'services')
            ->assertSee('Mis Servicios')
            ->assertDontSee('Mis Dominios');

        // Pero la entrada directa a dominios le da 403.
        Livewire::actingAs($member->refresh())
            ->test(\App\Livewire\Client\Domains\DomainList::class)
            ->assertForbidden();
    }
}
