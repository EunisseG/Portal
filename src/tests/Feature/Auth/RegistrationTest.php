<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_new_users_register_as_clients_with_profile(): void
    {
        $response = $this->post('/register', [
            'name' => 'Nuevo Cliente',
            'email' => 'nuevo@wortev.test',
            'password' => 'password-seguro-123',
            'password_confirmation' => 'password-seguro-123',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'nuevo@wortev.test')->firstOrFail();

        $this->assertTrue($user->hasRole(RoleName::Client->value));
        $this->assertFalse($user->isAdminArea());
        $this->assertNotNull($user->client, 'El registro debe crear el perfil Client.');
    }

    public function test_registration_queues_the_verification_email_instead_of_sending_it_inline(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $this->post('/register', [
            'name' => 'Cliente Cola',
            'email' => 'cola@wortev.test',
            'password' => 'password-seguro-123',
            'password_confirmation' => 'password-seguro-123',
        ])->assertRedirect();

        $user = User::where('email', 'cola@wortev.test')->firstOrFail();

        \Illuminate\Support\Facades\Notification::assertSentTo($user, \App\Notifications\QueuedVerifyEmail::class);
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            new \App\Notifications\QueuedVerifyEmail,
            'La verificación debe ir por la cola para que un SMTP lento no provoque un 504.',
        );
    }

    public function test_registration_does_not_wait_for_an_unreachable_smtp_server(): void
    {
        // SMTP del panel apuntando a un puerto que nadie escucha: antes, la
        // petición se colgaba hasta el 504. Ahora el correo se encola y el
        // registro responde sin intentar conectarse.
        \Illuminate\Support\Facades\Queue::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '10.255.255.1',
            'mail.mailers.smtp.port' => 587,
        ]);

        $start = microtime(true);
        $this->post('/register', [
            'name' => 'Cliente Rápido',
            'email' => 'rapido@wortev.test',
            'password' => 'password-seguro-123',
            'password_confirmation' => 'password-seguro-123',
        ])->assertRedirect();

        $this->assertLessThan(10, microtime(true) - $start, 'El registro no debe esperar al SMTP.');
        $this->assertDatabaseHas('users', ['email' => 'rapido@wortev.test']);
        \Illuminate\Support\Facades\Queue::assertPushed(\Illuminate\Notifications\SendQueuedNotifications::class);
    }

    public function test_smtp_timeout_is_set_so_requests_never_hang(): void
    {
        $this->assertSame(10, config('mail.mailers.smtp.timeout'));
        $this->assertLessThan(60, \App\Integrations\Mail\SmtpSettings::TIMEOUT_SECONDS);
    }
}
