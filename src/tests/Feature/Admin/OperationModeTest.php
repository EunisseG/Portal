<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Notifications\Models\Setting;
use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Models\Domain;
use App\Livewire\Admin\Domains\DomainIndex;
use App\Livewire\Admin\System\OperationModeSettings;
use App\Models\User;
use App\Support\OperationMode;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationModeTest extends TestCase
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

    public function test_defaults_to_manual(): void
    {
        $mode = app(OperationMode::class);
        $this->assertFalse($mode->paymentsAutomatic());
        $this->assertFalse($mode->domainRenewalAutomatic());
    }

    public function test_admin_can_switch_modes(): void
    {
        Livewire::actingAs($this->admin())
            ->test(OperationModeSettings::class)
            ->set('paymentsAutomatic', true)
            ->set('domainRenewalAutomatic', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(OperationMode::MODE_AUTOMATIC, Setting::get(OperationMode::KEY_PAYMENTS));
        $this->assertSame(OperationMode::MODE_MANUAL, Setting::get(OperationMode::KEY_DOMAIN_RENEWAL));
        $this->assertTrue(app(OperationMode::class)->paymentsAutomatic());
        $this->assertFalse(app(OperationMode::class)->domainRenewalAutomatic());
    }

    public function test_support_role_cannot_access_operation_mode(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $this->actingAs($support)
            ->get(route('admin.settings.operation'))
            ->assertForbidden();
    }

    public function test_admin_renews_domain_manually_for_chosen_years(): void
    {
        $domain = Domain::factory()->create([
            'status' => DomainStatus::Active->value,
            'expires_at' => now()->addMonths(2)->toDateString(),
        ]);

        $expected = $domain->expires_at->copy()->addYears(3)->toDateString();

        Livewire::actingAs($this->admin())
            ->test(DomainIndex::class)
            ->call('startRenew', $domain->id)
            ->assertSet('renewingId', $domain->id)
            ->set('renewYears', 3)
            ->call('renew')
            ->assertHasNoErrors()
            ->assertSet('renewingId', null);

        $domain->refresh();
        $this->assertSame($expected, $domain->expires_at->toDateString());
        $this->assertSame(DomainStatus::Active, $domain->status);
    }

    public function test_renewing_expired_domain_starts_from_today(): void
    {
        $domain = Domain::factory()->create([
            'status' => DomainStatus::Expired->value,
            'expires_at' => now()->subMonth()->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(DomainIndex::class)
            ->call('startRenew', $domain->id)
            ->set('renewYears', 1)
            ->call('renew');

        $domain->refresh();
        // Como ya venció, parte de hoy + 1 año.
        $this->assertSame(now()->addYear()->toDateString(), $domain->expires_at->toDateString());
        $this->assertSame(DomainStatus::Active, $domain->status);
    }

    public function test_renew_years_must_be_between_1_and_5(): void
    {
        $domain = Domain::factory()->create(['expires_at' => now()->addMonths(2)->toDateString()]);

        Livewire::actingAs($this->admin())
            ->test(DomainIndex::class)
            ->call('startRenew', $domain->id)
            ->set('renewYears', 7)
            ->call('renew')
            ->assertHasErrors('renewYears');

        // No cambió la fecha.
        $this->assertSame(now()->addMonths(2)->toDateString(), $domain->fresh()->expires_at->toDateString());
    }

    public function test_client_renewal_request_in_manual_mode_does_not_change_date(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        $client = \App\Domain\Clients\Models\Client::factory()->create(['user_id' => $user->id]);
        $domain = Domain::factory()->create([
            'client_id' => $client->id,
            'expires_at' => now()->addMonths(2)->toDateString(),
        ]);

        Livewire::actingAs($user->refresh())
            ->test(\App\Livewire\Client\Services\ServiceList::class)
            ->call('startRenew', $domain->id)
            ->set('renewYears', 2)
            ->call('renewDomain')
            ->assertHasNoErrors()
            ->assertSet('renewingId', null);

        // Manual: NO cambia la fecha del dominio (es una solicitud).
        $this->assertSame(now()->addMonths(2)->toDateString(), $domain->fresh()->expires_at->toDateString());
        // Queda registrada la solicitud.
        $this->assertDatabaseHas('integration_logs', ['event' => 'domain.renew_request']);
    }

    public function test_client_cannot_renew_another_clients_domain(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        \App\Domain\Clients\Models\Client::factory()->create(['user_id' => $user->id]);

        // Dominio de OTRO cliente.
        $alien = Domain::factory()->create(['expires_at' => now()->addMonths(2)->toDateString()]);

        Livewire::actingAs($user->refresh())
            ->test(\App\Livewire\Client\Services\ServiceList::class)
            ->set('renewingId', $alien->id)
            ->set('renewYears', 1)
            ->call('renewDomain')
            ->assertStatus(404);
    }
}
