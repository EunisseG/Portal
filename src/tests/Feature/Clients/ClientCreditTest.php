<?php

namespace Tests\Feature\Clients;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Enums\CreditMovementType;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientCreditService;
use App\Livewire\Admin\Clients\ClientCreditPanel;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function withRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_credit_adds_to_balance_and_records_movement(): void
    {
        $this->actingAs($this->withRole(RoleName::Admin));
        $client = Client::factory()->create(['credit_balance_cents' => 0]);

        app(ClientCreditService::class)->adjust($client, CreditMovementType::Credit, 50000, 'Anticipo');

        $client->refresh();
        $this->assertSame(50000, $client->credit_balance_cents);
        $this->assertSame(1, $client->creditMovements()->count());

        $movement = $client->creditMovements()->first();
        $this->assertSame(CreditMovementType::Credit, $movement->type);
        $this->assertSame(50000, $movement->balance_after_cents);
        $this->assertSame('Anticipo', $movement->reason);

        $this->assertDatabaseHas('audits', ['action' => 'client.credit_adjusted']);
    }

    public function test_debit_subtracts_but_never_goes_negative(): void
    {
        $this->actingAs($this->withRole(RoleName::Admin));
        $client = Client::factory()->create(['credit_balance_cents' => 30000]);
        $service = app(ClientCreditService::class);

        $service->adjust($client, CreditMovementType::Debit, 10000, 'Aplicado a factura');
        $this->assertSame(20000, $client->fresh()->credit_balance_cents);

        // Un cargo mayor al saldo lo deja en cero, no negativo.
        $service->adjust($client, CreditMovementType::Debit, 999999, 'Ajuste');
        $this->assertSame(0, $client->fresh()->credit_balance_cents);
    }

    public function test_admin_can_adjust_credit_via_component(): void
    {
        $admin = $this->withRole(RoleName::Admin);
        $client = Client::factory()->create(['credit_balance_cents' => 0]);

        Livewire::actingAs($admin)
            ->test(ClientCreditPanel::class, ['client' => $client])
            ->set('type', 'credit')
            ->set('amount', '150.50')
            ->set('reason', 'Nota de crédito')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(15050, $client->fresh()->credit_balance_cents);
    }

    public function test_billing_can_manage_credit(): void
    {
        $billing = $this->withRole(RoleName::Billing);
        $client = Client::factory()->create();

        Livewire::actingAs($billing)
            ->test(ClientCreditPanel::class, ['client' => $client])
            ->set('amount', '100')
            ->set('reason', 'Abono')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(10000, $client->fresh()->credit_balance_cents);
    }

    public function test_staff_cannot_manage_credit(): void
    {
        $staff = $this->withRole(RoleName::Staff);
        $client = Client::factory()->create();

        Livewire::actingAs($staff)
            ->test(ClientCreditPanel::class, ['client' => $client])
            ->assertForbidden();
    }

    public function test_adjust_requires_amount_and_reason(): void
    {
        $admin = $this->withRole(RoleName::Admin);
        $client = Client::factory()->create();

        Livewire::actingAs($admin)
            ->test(ClientCreditPanel::class, ['client' => $client])
            ->set('amount', '')
            ->set('reason', '')
            ->call('save')
            ->assertHasErrors(['amount', 'reason']);

        $this->assertSame(0, $client->fresh()->credit_balance_cents);
    }

    public function test_client_sees_credit_balance_read_only_on_dashboard(): void
    {
        $me = $this->clientUser();
        $me->client->update(['credit_balance_cents' => 25000]);

        $this->actingAs($me)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('Saldo a favor')
            ->assertSee('250.00 MXN');
    }

    public function test_client_cannot_access_admin_credit_panel(): void
    {
        $me = $this->clientUser();
        $other = Client::factory()->create();

        $this->actingAs($me)
            ->get(route('admin.clients.credit', $other))
            ->assertForbidden();
    }
}
