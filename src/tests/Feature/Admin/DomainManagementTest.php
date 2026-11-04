<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Models\Domain;
use App\Livewire\Admin\Domains\DomainForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DomainManagementTest extends TestCase
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

    public function test_admin_can_create_a_domain(): void
    {
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(DomainForm::class)
            ->set('client_id', (string) $client->id)
            ->set('name', 'ejemplo-demo.com')
            ->set('status', DomainStatus::Active->value)
            ->call('save')
            ->assertRedirect(route('admin.domains.index'));

        $this->assertDatabaseHas('domains', [
            'name' => 'ejemplo-demo.com',
            'client_id' => $client->id,
        ]);
        $this->assertDatabaseHas('audits', ['action' => AuditAction::DomainCreated->value]);
    }

    public function test_domain_name_must_be_unique(): void
    {
        $client = Client::factory()->create();
        Domain::factory()->create(['name' => 'ocupado.com']);

        Livewire::actingAs($this->admin())
            ->test(DomainForm::class)
            ->set('client_id', (string) $client->id)
            ->set('name', 'ocupado.com')
            ->call('save')
            ->assertHasErrors(['name']);
    }
}
