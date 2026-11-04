<?php

namespace Tests\Feature\Client;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientMemberService;
use App\Livewire\Client\Team\MemberManager;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ClientSubUserTest extends TestCase
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

    public function test_owner_can_create_a_sub_user_with_permissions(): void
    {
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(MemberManager::class)
            ->call('startCreate')
            ->set('name', 'Colaborador')
            ->set('email', 'colab@wortev.test')
            ->set('permissions', ['client.invoices.view', 'client.tickets.view'])
            ->call('save')
            ->assertHasNoErrors();

        $member = User::where('email', 'colab@wortev.test')->firstOrFail();
        $this->assertTrue($member->isSubUser());
        $this->assertSame($owner->client->id, $member->parent_client_id);
        $this->assertTrue($member->hasRole(RoleName::Client->value));
        $this->assertTrue($member->can('client.invoices.view'));
        $this->assertFalse($member->can('client.domains.view'));
        // El sub-usuario comparte la cuenta del titular.
        $this->assertSame($owner->client->id, $member->clientAccount()->id);
    }

    public function test_limit_is_one_sub_user(): void
    {
        $owner = $this->owner();
        $members = app(ClientMemberService::class);

        $members->create($owner->client, ['name' => 'Uno', 'email' => 'uno@wortev.test']);

        $this->expectException(ValidationException::class);
        $members->create($owner->client, ['name' => 'Dos', 'email' => 'dos@wortev.test']);
    }

    public function test_sub_user_cannot_access_team_management(): void
    {
        $owner = $this->owner();
        $member = app(ClientMemberService::class)->create($owner->client, [
            'name' => 'Colab', 'email' => 'colab@wortev.test',
        ]);

        Livewire::actingAs($member)
            ->test(MemberManager::class)
            ->assertForbidden();
    }

    public function test_sub_user_without_permission_is_blocked_from_section(): void
    {
        $owner = $this->owner();
        $member = app(ClientMemberService::class)->create($owner->client, [
            'name' => 'Colab',
            'email' => 'colab@wortev.test',
            'permissions' => ['client.invoices.view'], // sin dominios
        ]);

        // Tiene facturas: pasa.
        Livewire::actingAs($member)
            ->test(\App\Livewire\Client\Invoices\InvoiceList::class)
            ->assertOk();

        // No tiene dominios: 403.
        Livewire::actingAs($member)
            ->test(\App\Livewire\Client\Domains\DomainList::class)
            ->assertForbidden();
    }

    public function test_sub_user_sees_owner_data(): void
    {
        $owner = $this->owner();
        \App\Domain\Services\Models\Service::factory()->create([
            'client_id' => $owner->client->id,
            'name' => 'Servicio del titular',
        ]);

        $member = app(ClientMemberService::class)->create($owner->client, [
            'name' => 'Colab',
            'email' => 'colab@wortev.test',
            'permissions' => ['client.services.view'],
        ]);

        Livewire::actingAs($member)
            ->test(\App\Livewire\Client\Services\ServiceList::class)
            ->assertOk()
            ->assertSee('Servicio del titular');
    }

    public function test_owner_can_block_and_delete_sub_user(): void
    {
        $owner = $this->owner();
        $member = app(ClientMemberService::class)->create($owner->client, [
            'name' => 'Colab', 'email' => 'colab@wortev.test',
        ]);

        Livewire::actingAs($owner)
            ->test(MemberManager::class)
            ->call('toggleBlock', $member->id);
        $this->assertTrue($member->fresh()->isBlocked());

        Livewire::actingAs($owner)
            ->test(MemberManager::class)
            ->call('delete', $member->id);
        $this->assertNull(User::find($member->id));
    }
}
