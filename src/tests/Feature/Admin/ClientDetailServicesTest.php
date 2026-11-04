<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use App\Livewire\Admin\Clients\ClientShow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientDetailServicesTest extends TestCase
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

    public function test_client_detail_shows_their_services_and_domains(): void
    {
        $client = Client::factory()->create();

        Service::factory()->create([
            'client_id' => $client->id,
            'name' => 'Servicio Visible En Detalle',
        ]);
        Domain::factory()->create([
            'client_id' => $client->id,
            'name' => 'dominioendetalle.com',
        ]);

        // El detalle está segmentado en pestañas; se verifica cada una.
        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->assertSee('Contratar servicio')
            ->call('setTab', 'servicios')
            ->assertSee('Servicio Visible En Detalle')
            ->call('setTab', 'dominios')
            ->assertSee('dominioendetalle.com');
    }

    public function test_client_detail_shows_their_invoices_and_upload_button(): void
    {
        $client = Client::factory()->create();

        Invoice::factory()->create([
            'client_id' => $client->id,
            'folio' => 'FAC-EN-DETALLE-001',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->call('setTab', 'facturas')
            ->assertSee('FAC-EN-DETALLE-001')
            ->assertSee('Subir factura');
    }
}
