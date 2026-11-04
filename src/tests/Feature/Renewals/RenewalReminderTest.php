<?php

namespace Tests\Feature\Renewals;

use App\Domain\Clients\Models\Client;
use App\Domain\Notifications\Jobs\SendRenewalReminder;
use App\Domain\Notifications\Models\Reminder;
use App\Domain\Notifications\Services\UpcomingRenewals;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\Service;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\MailGateway;
use App\Integrations\Contracts\WhatsAppGateway;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RenewalReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_upcoming_renewals_finds_items_exactly_in_days(): void
    {
        $client = Client::factory()->create();

        Service::factory()->create([
            'client_id' => $client->id,
            'status' => ServiceStatus::Active->value,
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);
        // Otro que vence en 8 días: NO debe aparecer para el umbral de 7.
        Service::factory()->create([
            'client_id' => $client->id,
            'status' => ServiceStatus::Active->value,
            'expires_at' => now()->addDays(8)->toDateString(),
        ]);

        $items = app(UpcomingRenewals::class)->exactlyInDays(7);

        $this->assertCount(1, $items);
        $this->assertSame('service', $items->first()->type);
    }

    public function test_command_queues_reminders_without_duplicating(): void
    {
        Queue::fake();
        $client = Client::factory()->create();

        Service::factory()->create([
            'client_id' => $client->id,
            'status' => ServiceStatus::Active->value,
            'expires_at' => now()->addDays(30)->toDateString(),
        ]);

        // Primera corrida: encola 1 (umbral 30).
        $this->artisan('reminders:send')->assertExitCode(0);
        Queue::assertPushed(SendRenewalReminder::class, 1);

        // Simula que ya se registró el recordatorio (como haría el job).
        $service = Service::first();
        Reminder::create([
            'remindable_type' => $service->getMorphClass(),
            'remindable_id' => $service->id,
            'days_before' => 30,
            'due_date' => now()->addDays(30)->toDateString(),
            'sent_at' => now(),
        ]);

        // Segunda corrida: no debe volver a encolar ese recordatorio.
        Queue::fake();
        $this->artisan('reminders:send')->assertExitCode(0);
        Queue::assertNothingPushed();
    }

    public function test_job_sends_to_client_and_internal_and_records_reminder(): void
    {
        config()->set('renewals.notify_email', 'interno@wortev.test');

        $client = Client::factory()->create(['whatsapp' => '+52 55 1111 2222']);
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'name' => 'Hosting Prueba',
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);

        $mail = Mockery::mock(MailGateway::class);
        // Un correo al cliente y otro interno.
        $mail->shouldReceive('send')->twice()->andReturn(GatewayResult::ok());

        $whatsapp = Mockery::mock(WhatsAppGateway::class);
        $whatsapp->shouldReceive('sendMessage')->once()->andReturn(GatewayResult::ok());

        (new SendRenewalReminder('service', $service->id, 7, now()->addDays(7)->toDateString()))
            ->handle($mail, $whatsapp);

        $this->assertDatabaseHas('reminders', [
            'remindable_id' => $service->id,
            'days_before' => 7,
        ]);
    }

    public function test_job_also_notifies_the_services_sales_rep(): void
    {
        config()->set('renewals.notify_email', 'interno@wortev.test');

        $rep = \App\Domain\Sales\Models\SalesRep::factory()->create([
            'email' => 'ejecutivo@wortev.test',
            'phone' => '+52 55 9999 0000',
        ]);
        $client = Client::factory()->create(['whatsapp' => '+52 55 1111 2222']);
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'sales_rep_id' => $rep->id,
            'name' => 'Hosting con ejecutivo',
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);

        $mail = Mockery::mock(MailGateway::class);
        // Cliente + interno + ejecutivo = 3 correos.
        $mail->shouldReceive('send')->times(3)->andReturn(GatewayResult::ok());

        $whatsapp = Mockery::mock(WhatsAppGateway::class);
        // Cliente + ejecutivo = 2 mensajes de WhatsApp.
        $whatsapp->shouldReceive('sendMessage')->twice()->andReturn(GatewayResult::ok());

        (new SendRenewalReminder('service', $service->id, 7, now()->addDays(7)->toDateString()))
            ->handle($mail, $whatsapp);

        $this->assertDatabaseHas('reminders', [
            'remindable_id' => $service->id,
            'days_before' => 7,
        ]);
    }

    public function test_addon_reminder_notifies_sales_rep_of_parent_service(): void
    {
        $rep = \App\Domain\Sales\Models\SalesRep::factory()->create([
            'email' => 'ejec@wortev.test',
            'phone' => null,
        ]);
        $client = Client::factory()->create(['whatsapp' => null]);
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'sales_rep_id' => $rep->id,
        ]);
        $addon = \App\Domain\Services\Models\Addon::factory()->create([
            'service_id' => $service->id,
            'name' => 'Complemento X',
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);

        $mail = Mockery::mock(MailGateway::class);
        // Cliente sin email (depende de la factory) — nos basta con afirmar que
        // el correo al ejecutivo se envía al menos una vez.
        $mail->shouldReceive('send')->atLeast()->once()->andReturn(GatewayResult::ok());
        $whatsapp = Mockery::mock(WhatsAppGateway::class);
        $whatsapp->shouldReceive('sendMessage')->zeroOrMoreTimes()->andReturn(GatewayResult::ok());

        (new SendRenewalReminder('addon', $addon->id, 7, now()->addDays(7)->toDateString()))
            ->handle($mail, $whatsapp);

        $this->assertDatabaseHas('reminders', [
            'remindable_id' => $addon->id,
            'days_before' => 7,
        ]);
    }

    public function test_job_does_not_resend_if_reminder_exists(): void
    {
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);

        Reminder::create([
            'remindable_type' => $service->getMorphClass(),
            'remindable_id' => $service->id,
            'days_before' => 7,
            'due_date' => now()->addDays(7)->toDateString(),
            'sent_at' => now(),
        ]);

        $mail = Mockery::mock(MailGateway::class);
        $mail->shouldNotReceive('send');
        $whatsapp = Mockery::mock(WhatsAppGateway::class);
        $whatsapp->shouldNotReceive('sendMessage');

        (new SendRenewalReminder('service', $service->id, 7, now()->addDays(7)->toDateString()))
            ->handle($mail, $whatsapp);

        // Sigue habiendo solo un registro.
        $this->assertSame(1, Reminder::count());
    }
}
