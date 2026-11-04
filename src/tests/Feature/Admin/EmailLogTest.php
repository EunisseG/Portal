<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Notifications\Models\EmailLog;
use App\Integrations\Contracts\MailGateway;
use App\Livewire\Admin\Activity\EmailLogIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailLogTest extends TestCase
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

    public function test_sending_mail_creates_an_email_log(): void
    {
        app(MailGateway::class)->send('destino@wortev.test', 'Asunto de prueba', 'Cuerpo del mensaje', ['k' => 'v']);

        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'destino@wortev.test',
            'subject' => 'Asunto de prueba',
            'success' => true,
        ]);
    }

    public function test_test_email_is_also_logged(): void
    {
        app(MailGateway::class)->sendTestEmail('prueba@wortev.test');

        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'prueba@wortev.test',
            'subject' => 'Correo de prueba',
        ]);
    }

    public function test_admin_can_view_and_inspect_email_logs(): void
    {
        EmailLog::create([
            'recipient' => 'visible@wortev.test',
            'subject' => 'Bienvenida',
            'body' => 'Contenido inspeccionable',
            'context' => ['type' => 'welcome'],
            'success' => true,
            'sent_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(EmailLogIndex::class)
            ->assertSee('visible@wortev.test')
            ->assertSee('Bienvenida')
            ->call('show', EmailLog::first()->id)
            ->assertSee('Contenido inspeccionable');
    }

    public function test_admin_can_delete_an_email_log(): void
    {
        $log = EmailLog::create([
            'recipient' => 'x@wortev.test',
            'subject' => 'Borrar',
            'success' => true,
            'sent_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(EmailLogIndex::class)
            ->call('delete', $log->id);

        $this->assertDatabaseMissing('email_logs', ['id' => $log->id]);
    }

    public function test_client_cannot_view_email_logs(): void
    {
        $client = User::factory()->create();
        $client->assignRole(RoleName::Client->value);

        $this->actingAs($client)
            ->get(route('admin.activity.emails'))
            ->assertForbidden();
    }
}
