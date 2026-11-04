<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Admin Prueba']);
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_users_page_shows_table_controls_and_data(): void
    {
        // La pestaña por defecto es "internos"; el objetivo debe ser interno.
        $target = User::factory()->create(['email' => 'visible@wortev.test']);
        $target->assignRole(RoleName::Staff->value);

        $this->actingAs($this->admin())
            ->get('/admin/usuarios')
            ->assertOk()
            ->assertSee('Nuevo usuario')
            ->assertSee('Buscar por nombre')
            ->assertSee('Usuarios internos')
            ->assertSee('visible@wortev.test')
            ->assertSee('Bloquear');
    }

    public function test_users_page_clients_tab_shows_client_users(): void
    {
        $target = User::factory()->create(['email' => 'clienteuser@wortev.test']);
        $target->assignRole(RoleName::Client->value);

        $this->actingAs($this->admin())
            ->get('/admin/usuarios?tab=clients')
            ->assertOk()
            ->assertSee('clienteuser@wortev.test');
    }

    public function test_clients_page_shows_table_controls_and_data(): void
    {
        $client = Client::factory()->create(['company' => 'Empresa Visible SA']);
        $client->user->assignRole(RoleName::Client->value);

        $this->actingAs($this->admin())
            ->get('/admin/clientes')
            ->assertOk()
            ->assertSee('Nuevo cliente')
            ->assertSee('Introduzca el texto a buscar')
            ->assertSee('Empresa Visible SA')
            // Nuevo diseño: tarjetas KPI de estado + gráfica.
            ->assertSee('Activo')
            ->assertSee('Suspendido')
            ->assertSee('Cancelado')
            ->assertSee('Total')
            ->assertSee('Distribución de clientes por estado');
    }

    public function test_top_navigation_shows_groups_and_placeholders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            // Grupos principales de la barra superior.
            ->assertSee('Productos')
            ->assertSee('Soporte')
            ->assertSee('Sistema')
            ->assertSee('Seguridad')
            ->assertSee('Actividad')
            ->assertSee('Anuncios')
            // Ítems de submenú existentes.
            ->assertSee('Pedidos')
            ->assertSee('Gráficas')
            ->assertSee('Tickets de clientes')
            ->assertSee('Tickets internos')
            // Placeholders de módulos aún no construidos.
            ->assertSee('Respuestas preparadas')
            ->assertSee('Tipos de moneda')
            ->assertSee('Mantenimientos');
    }
}
