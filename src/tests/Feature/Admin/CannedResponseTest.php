<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Tickets\Models\CannedResponse;
use App\Domain\Tickets\Models\Ticket;
use App\Livewire\Admin\Support\CannedResponseIndex;
use App\Livewire\Admin\Tickets\TicketShow as AdminTicketShow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CannedResponseTest extends TestCase
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

    public function test_admin_can_create_a_canned_response(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CannedResponseIndex::class)
            ->set('title', 'Saludo inicial')
            ->set('body', 'Hola, gracias por contactarnos.')
            ->set('sort_order', 1)
            ->call('save');

        $this->assertDatabaseHas('canned_responses', [
            'title' => 'Saludo inicial',
            'body' => 'Hola, gracias por contactarnos.',
        ]);
    }

    public function test_admin_can_edit_a_canned_response(): void
    {
        $canned = CannedResponse::create(['title' => 'Viejo', 'body' => 'Texto viejo']);

        Livewire::actingAs($this->admin())
            ->test(CannedResponseIndex::class)
            ->call('edit', $canned->id)
            ->set('title', 'Nuevo título')
            ->call('save');

        $this->assertDatabaseHas('canned_responses', [
            'id' => $canned->id,
            'title' => 'Nuevo título',
        ]);
    }

    public function test_admin_can_delete_a_canned_response(): void
    {
        $canned = CannedResponse::create(['title' => 'Borrar', 'body' => 'Adiós']);

        Livewire::actingAs($this->admin())
            ->test(CannedResponseIndex::class)
            ->call('delete', $canned->id);

        $this->assertDatabaseMissing('canned_responses', ['id' => $canned->id]);
    }

    public function test_title_and_body_are_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CannedResponseIndex::class)
            ->set('title', '')
            ->set('body', '')
            ->call('save')
            ->assertHasErrors(['title', 'body']);
    }

    public function test_billing_role_cannot_manage_canned_responses(): void
    {
        $billing = User::factory()->create();
        $billing->assignRole(RoleName::Billing->value);

        $this->actingAs($billing)
            ->get(route('admin.canned.index'))
            ->assertForbidden();
    }

    public function test_applying_canned_fills_reply_body(): void
    {
        $canned = CannedResponse::create(['title' => 'Cierre', 'body' => 'Quedamos atentos, saludos.']);
        $ticket = Ticket::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(AdminTicketShow::class, ['ticket' => $ticket])
            ->call('applyCanned', $canned->id)
            ->assertSet('body', 'Quedamos atentos, saludos.');
    }
}
