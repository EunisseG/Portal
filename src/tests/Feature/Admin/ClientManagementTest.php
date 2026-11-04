<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Livewire\Admin\Clients\ClientForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientManagementTest extends TestCase
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

    private function makeClient(\App\Domain\Clients\Enums\ClientStatus $status): Client
    {
        return Client::factory()->create(['status' => $status->value]);
    }

    public function test_admin_can_create_a_client(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ClientForm::class)
            ->set('name', 'Cliente Nuevo')
            ->set('email', 'clientenuevo@wortev.test')
            ->set('company', 'ACME S.A.')
            ->set('phone', '555-1234')
            ->call('save')
            ->assertRedirect(route('admin.clients.index'));

        $user = User::where('email', 'clientenuevo@wortev.test')->firstOrFail();
        $this->assertTrue($user->hasRole(RoleName::Client->value));
        $this->assertNotNull($user->client);
        $this->assertSame('ACME S.A.', $user->client->company);

        $this->assertDatabaseHas('audits', ['action' => AuditAction::ClientCreated->value]);
    }

    public function test_admin_can_update_a_client(): void
    {
        $client = Client::factory()->create();
        $client->user->assignRole(RoleName::Client->value);

        Livewire::actingAs($this->admin())
            ->test(ClientForm::class, ['client' => $client])
            ->set('name', 'Nombre Editado')
            ->set('company', 'Nueva Empresa')
            ->call('save')
            ->assertRedirect(route('admin.clients.index'));

        $client->refresh();
        $this->assertSame('Nombre Editado', $client->user->name);
        $this->assertSame('Nueva Empresa', $client->company);

        $this->assertDatabaseHas('audits', ['action' => AuditAction::ClientUpdated->value]);
    }

    public function test_admin_can_update_all_client_fields(): void
    {
        $client = Client::factory()->create();
        $client->user->assignRole(RoleName::Client->value);

        Livewire::actingAs($this->admin())
            ->test(ClientForm::class, ['client' => $client])
            ->set('name', 'Ana')
            ->set('last_name', 'López')
            ->set('secondary_email', 'ana.alt@wortev.test')
            ->set('company', 'ACME S.A.')
            ->set('tax_id', 'XAXX010101000')
            ->set('phone', '555-1000')
            ->set('whatsapp', '555-2000')
            ->set('address_line1', 'Av. Reforma 100')
            ->set('address_line2', 'Piso 5')
            ->set('city', 'CDMX')
            ->set('state', 'Ciudad de México')
            ->set('postal_code', '06600')
            ->set('country', 'MX')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.clients.index'));

        $client->refresh();
        $this->assertSame('Ana', $client->user->name);
        $this->assertSame('López', $client->user->last_name);
        $this->assertSame('ana.alt@wortev.test', $client->user->secondary_email);
        $this->assertSame('ACME S.A.', $client->company);
        $this->assertSame('XAXX010101000', $client->tax_id);
        $this->assertSame('Av. Reforma 100', $client->address_line1);
        $this->assertSame('Piso 5', $client->address_line2);
        $this->assertSame('CDMX', $client->city);
        $this->assertSame('Ciudad de México', $client->state);
        $this->assertSame('06600', $client->postal_code);
        $this->assertSame('MX', $client->country);
    }

    public function test_creating_client_with_initial_product_creates_a_service(): void
    {
        $product = \App\Domain\Services\Models\Product::create([
            'name' => 'Respaldo diario',
            'price_cents' => 15000,
            'currency' => 'MXN',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ClientForm::class)
            ->set('name', 'Con Servicio')
            ->set('email', 'conservicio@wortev.test')
            ->set('addProduct', true)
            ->set('productId', $product->id)
            ->call('save')
            ->assertRedirect(route('admin.clients.index'));

        $client = User::where('email', 'conservicio@wortev.test')->firstOrFail()->client;
        $this->assertDatabaseHas('services', [
            'client_id' => $client->id,
            'name' => 'Respaldo diario',
            'total_cents' => 15000,
        ]);
    }

    public function test_creating_client_without_product_does_not_create_a_service(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ClientForm::class)
            ->set('name', 'Sin Servicio')
            ->set('email', 'sinservicio@wortev.test')
            ->call('save');

        $client = User::where('email', 'sinservicio@wortev.test')->firstOrFail()->client;
        $this->assertSame(0, $client->services()->count());
    }

    public function test_product_is_required_when_add_product_is_checked(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ClientForm::class)
            ->set('name', 'Falta Producto')
            ->set('email', 'faltaproducto@wortev.test')
            ->set('addProduct', true)
            ->call('save')
            ->assertHasErrors('productId');
    }

    public function test_cannot_register_client_with_existing_email(): void
    {
        // Ya existe un usuario con ese correo.
        $existing = User::factory()->create(['email' => 'repetido@wortev.test']);

        Livewire::actingAs($this->admin())
            ->test(ClientForm::class)
            ->set('name', 'Duplicado')
            ->set('email', 'repetido@wortev.test')
            ->call('save')
            ->assertHasErrors(['email' => 'unique']);

        // No se creó un segundo usuario con ese correo.
        $this->assertSame(1, User::where('email', 'repetido@wortev.test')->count());
    }

    public function test_admin_can_delete_a_client_with_all_its_data(): void
    {
        $client = Client::factory()->create();
        $client->user->assignRole(RoleName::Client->value);

        // Sub-usuario del cliente.
        $member = app(\App\Domain\Clients\Services\ClientMemberService::class)->create($client, [
            'name' => 'Colab', 'email' => 'colab-del@wortev.test',
        ]);

        // Datos ligados.
        $service = \App\Domain\Services\Models\Service::factory()->create(['client_id' => $client->id]);
        \App\Domain\Services\Models\Domain::factory()->create(['client_id' => $client->id]);

        $ownerId = $client->user->id;
        $clientId = $client->id;

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->call('delete', $clientId);

        // El cliente, su titular y sub-usuario desaparecen (sin huérfanos).
        $this->assertDatabaseMissing('clients', ['id' => $clientId]);
        $this->assertDatabaseMissing('users', ['id' => $ownerId]);
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        // Y sus datos ligados caen en cascada.
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
        $this->assertDatabaseMissing('domains', ['client_id' => $clientId]);

        $this->assertDatabaseHas('audits', ['action' => AuditAction::ClientDeleted->value]);
    }

    public function test_clients_status_chart_has_distribution_data(): void
    {
        $this->makeClient(\App\Domain\Clients\Enums\ClientStatus::Active);
        $this->makeClient(\App\Domain\Clients\Enums\ClientStatus::Active);
        $this->makeClient(\App\Domain\Clients\Enums\ClientStatus::Suspended);
        $this->makeClient(\App\Domain\Clients\Enums\ClientStatus::Cancelled);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->assertSee('Distribución de clientes por estado')
            // chart['data'] = [activo, suspendido, cancelado]
            ->assertViewHas('chart', fn ($chart) => $chart['data'] === [2, 1, 1]
                && count($chart['labels']) === 3);
    }

    public function test_admin_can_suspend_and_reactivate_a_client(): void
    {
        $client = Client::factory()->create();
        $client->user->assignRole(RoleName::Client->value);

        // Por defecto está activo (el default lo aplica la BD).
        $this->assertSame(\App\Domain\Clients\Enums\ClientStatus::Active, $client->fresh()->status);

        // Suspender: bloquea el acceso del titular.
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->call('setStatus', $client->id, 'suspended');

        $client->refresh();
        $this->assertSame(\App\Domain\Clients\Enums\ClientStatus::Suspended, $client->status);
        $this->assertTrue($client->user->fresh()->isBlocked());
        $this->assertDatabaseHas('audits', ['action' => \App\Domain\Audit\Enums\AuditAction::ClientStatusChanged->value]);

        // Reactivar: restaura el acceso.
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->call('setStatus', $client->id, 'active');

        $client->refresh();
        $this->assertSame(\App\Domain\Clients\Enums\ClientStatus::Active, $client->status);
        $this->assertFalse($client->user->fresh()->isBlocked());
    }

    public function test_suspending_client_also_blocks_sub_users(): void
    {
        $client = Client::factory()->create();
        $client->user->assignRole(RoleName::Client->value);

        // Sub-usuario de la cuenta.
        $member = app(\App\Domain\Clients\Services\ClientMemberService::class)->create($client, [
            'name' => 'Colab', 'email' => 'colab-estado@wortev.test',
        ]);
        $this->assertFalse($member->fresh()->isBlocked());

        // Suspender el cliente bloquea titular + sub-usuario.
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->call('setStatus', $client->id, 'suspended');

        $this->assertTrue($client->user->fresh()->isBlocked());
        $this->assertTrue($member->fresh()->isBlocked());

        // Reactivar restaura el acceso de toda la cuenta.
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->call('setStatus', $client->id, 'active');

        $this->assertFalse($client->user->fresh()->isBlocked());
        $this->assertFalse($member->fresh()->isBlocked());
    }

    public function test_admin_can_cancel_a_client_and_block_access(): void
    {
        $client = Client::factory()->create();
        $client->user->assignRole(RoleName::Client->value);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->call('setStatus', $client->id, 'cancelled');

        $client->refresh();
        $this->assertSame(\App\Domain\Clients\Enums\ClientStatus::Cancelled, $client->status);
        $this->assertTrue($client->user->fresh()->isBlocked());
    }

    public function test_support_role_cannot_change_client_status(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $client = Client::factory()->create();

        Livewire::actingAs($support)
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->call('setStatus', $client->id, 'suspended')
            ->assertForbidden();

        $this->assertSame(\App\Domain\Clients\Enums\ClientStatus::Active, $client->fresh()->status);
    }

    public function test_support_role_cannot_delete_a_client(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $client = Client::factory()->create();

        Livewire::actingAs($support)
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->call('delete', $client->id)
            ->assertForbidden();

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_cannot_delete_client_with_stamped_invoice(): void
    {
        $client = Client::factory()->create();
        $client->user->assignRole(RoleName::Client->value);

        // Factura timbrada (con folio fiscal UUID).
        \App\Domain\Billing\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'uuid' => 'A1B2C3D4-0000-1111-2222-333344445555',
            'folio' => 'F-001',
        ]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->call('delete', $client->id);

        // No se borró: salvaguarda fiscal.
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('users', ['id' => $client->user->id]);
    }

    public function test_can_delete_client_with_only_unstamped_invoices(): void
    {
        $client = Client::factory()->create();
        $client->user->assignRole(RoleName::Client->value);

        // Factura NO timbrada (sin uuid ni folio): es borrable.
        \App\Domain\Billing\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'uuid' => null,
            'folio' => null,
        ]);

        $clientId = $client->id;

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->call('delete', $clientId);

        $this->assertDatabaseMissing('clients', ['id' => $clientId]);
    }

    public function test_clients_list_can_be_filtered_by_sales_rep(): void
    {
        $rep = \App\Domain\Sales\Models\SalesRep::factory()->create();

        $mine = Client::factory()->create(['company' => 'Cartera Rep SA']);
        \App\Domain\Services\Models\Service::factory()->create(['client_id' => $mine->id, 'sales_rep_id' => $rep->id]);
        $other = Client::factory()->create(['company' => 'Otra Empresa SA']);
        \App\Domain\Services\Models\Service::factory()->create(['client_id' => $other->id, 'sales_rep_id' => null]);

        // Filtrado por el ejecutivo: solo su cliente.
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->set('salesRepFilter', (string) $rep->id)
            ->assertSee('Cartera Rep SA')
            ->assertDontSee('Otra Empresa SA');

        // Filtro "sin asignar": solo el que no tiene ejecutivo.
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Clients\ClientIndex::class)
            ->set('salesRepFilter', '0')
            ->assertSee('Otra Empresa SA')
            ->assertDontSee('Cartera Rep SA');
    }
}
