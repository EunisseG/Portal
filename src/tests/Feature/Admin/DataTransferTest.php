<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DataTransferTest extends TestCase
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

    private function csvUpload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    public function test_admin_can_export_clients(): void
    {
        $this->freezeTime();

        Excel::fake();

        Client::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.clients.export'))
            ->assertOk();

        Excel::assertDownloaded('clientes-'.now()->format('Ymd-His').'.xlsx');
    }

    public function test_admin_can_export_services(): void
    {
        Excel::fake();

        Service::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.services.export'))
            ->assertOk();
    }

    public function test_admin_can_import_clients_from_csv(): void
    {
        $csv = "nombre,correo,empresa,telefono,whatsapp,rfc,direccion\n"
            ."Empresa Uno,uno@import.test,Acme SA,555111,555222,ABC010101XYZ,Calle 1\n";

        $file = $this->csvUpload('clientes.csv', $csv);

        $this->actingAs($this->admin())
            ->post(route('admin.clients.import'), ['file' => $file])
            ->assertRedirect(route('admin.clients.index'));

        $this->assertDatabaseHas('users', ['email' => 'uno@import.test', 'name' => 'Empresa Uno']);
        $this->assertDatabaseHas('clients', ['company' => 'Acme SA', 'tax_id' => 'ABC010101XYZ']);

        // El usuario importado tiene rol client.
        $user = User::where('email', 'uno@import.test')->first();
        $this->assertTrue($user->hasRole(RoleName::Client->value));
    }

    public function test_admin_can_import_services_linked_to_existing_client(): void
    {
        $client = Client::factory()->create();
        $email = $client->user->email;

        $csv = "cliente_correo,servicio,tipo,ciclo,total,moneda,inicia,vence\n"
            .$email.",Hosting Importado,hosting,annual,1200.00,MXN,2026-01-01,2026-12-31\n"
            ."inexistente@nadie.test,Servicio Huerfano,hosting,annual,500,MXN,,\n";

        $file = $this->csvUpload('servicios.csv', $csv);

        $this->actingAs($this->admin())
            ->post(route('admin.services.import'), ['file' => $file])
            ->assertRedirect(route('admin.services.index'));

        // Solo el servicio con cliente existente se crea.
        $this->assertDatabaseHas('services', [
            'client_id' => $client->id,
            'name' => 'Hosting Importado',
            'total_cents' => 120000,
        ]);
        $this->assertDatabaseMissing('services', ['name' => 'Servicio Huerfano']);
    }

    public function test_import_requires_a_file(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.clients.import'), [])
            ->assertSessionHasErrors('file');
    }

    public function test_client_cannot_export(): void
    {
        $client = User::factory()->create();
        $client->assignRole(RoleName::Client->value);

        $this->actingAs($client)
            ->get(route('admin.clients.export'))
            ->assertForbidden();
    }

    public function test_admin_can_export_clients_as_csv(): void
    {
        $this->freezeTime();
        Excel::fake();

        Client::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.clients.export', ['format' => 'csv']))
            ->assertOk();

        Excel::assertDownloaded('clientes-'.now()->format('Ymd-His').'.csv');
    }

    public function test_admin_can_export_report_as_excel_and_csv(): void
    {
        $this->freezeTime();
        Excel::fake();

        $this->actingAs($this->admin())
            ->get(route('admin.reports.export'))
            ->assertOk();
        Excel::assertDownloaded('reporte-'.now()->format('Ymd-His').'.xlsx');

        $this->actingAs($this->admin())
            ->get(route('admin.reports.export', ['format' => 'csv']))
            ->assertOk();
        Excel::assertDownloaded('reporte-'.now()->format('Ymd-His').'.csv');
    }

    public function test_support_cannot_export_report(): void
    {
        // support no tiene invoices.view (gate de la pantalla de gráficas).
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $this->actingAs($support)
            ->get(route('admin.reports.export'))
            ->assertForbidden();
    }

    public function test_clients_export_query_filters_by_date_range(): void
    {
        $old = Client::factory()->create();
        $old->forceFill(['created_at' => '2026-01-10'])->save();

        $recent = Client::factory()->create();
        $recent->forceFill(['created_at' => '2026-06-10'])->save();

        $export = new \App\Exports\ClientsExport('2026-06-01', '2026-06-30');
        $ids = $export->query()->pluck('id')->all();

        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_clients_export_query_filters_by_sales_rep(): void
    {
        $rep = \App\Domain\Sales\Models\SalesRep::factory()->create();
        $mine = Client::factory()->create();
        \App\Domain\Services\Models\Service::factory()->create(['client_id' => $mine->id, 'sales_rep_id' => $rep->id]);
        $other = Client::factory()->create();
        \App\Domain\Services\Models\Service::factory()->create(['client_id' => $other->id, 'sales_rep_id' => null]);

        $export = new \App\Exports\ClientsExport(null, null, (string) $rep->id);
        $ids = $export->query()->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($other->id, $ids);

        // Filtro "sin asignar".
        $unassigned = new \App\Exports\ClientsExport(null, null, '0');
        $ids2 = $unassigned->query()->pluck('id')->all();
        $this->assertContains($other->id, $ids2);
        $this->assertNotContains($mine->id, $ids2);
    }

    public function test_services_export_query_filters_by_date_range(): void
    {
        $old = Service::factory()->create();
        $old->forceFill(['created_at' => '2026-01-10'])->save();

        $recent = Service::factory()->create();
        $recent->forceFill(['created_at' => '2026-06-10'])->save();

        $export = new \App\Exports\ServicesExport(null, null, '2026-06-01', '2026-06-30');
        $ids = $export->query()->pluck('id')->all();

        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }
}
