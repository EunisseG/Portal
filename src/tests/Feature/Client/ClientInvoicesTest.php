<?php

namespace Tests\Feature\Client;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\InvoiceService;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientDashboardSummary;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake(InvoiceService::DISK);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_client_sees_only_their_own_invoices(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();

        Invoice::factory()->create(['client_id' => $me->client->id, 'folio' => 'MIA-001']);
        Invoice::factory()->create(['client_id' => $other->client->id, 'folio' => 'AJENA-999']);

        $this->actingAs($me)
            ->get('/client/facturas')
            ->assertOk()
            ->assertSee('MIA-001')
            ->assertDontSee('AJENA-999');
    }

    public function test_client_can_download_own_invoice_but_not_others(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();

        $mine = Invoice::factory()->create(['client_id' => $me->client->id]);
        Storage::disk(InvoiceService::DISK)->put($mine->pdf_path, '%PDF-1.4 demo');

        $theirs = Invoice::factory()->create(['client_id' => $other->client->id]);
        Storage::disk(InvoiceService::DISK)->put($theirs->pdf_path, '%PDF-1.4 demo');

        // Puede descargar la suya.
        $this->actingAs($me)
            ->get(route('client.invoices.download', $mine))
            ->assertOk();

        // No puede descargar la de otro cliente.
        $this->actingAs($me)
            ->get(route('client.invoices.download', $theirs))
            ->assertForbidden();
    }

    public function test_dashboard_summary_counts_invoices(): void
    {
        $client = Client::factory()->create();

        Invoice::factory()->count(2)->create(['client_id' => $client->id, 'status' => InvoiceStatus::Pending->value]);
        Invoice::factory()->create(['client_id' => $client->id, 'status' => InvoiceStatus::Paid->value]);

        $summary = app(ClientDashboardSummary::class)->for($client);

        $this->assertSame(3, $summary['invoices']['total']);
        $this->assertSame(1, $summary['invoices']['paid']);
        $this->assertSame(2, $summary['invoices']['unpaid']);
    }

    public function test_pay_online_button_hidden_in_manual_mode(): void
    {
        $me = $this->clientUser();
        Invoice::factory()->create([
            'client_id' => $me->client->id,
            'folio' => 'MANUAL-001',
            'status' => InvoiceStatus::Pending->value,
        ]);

        // Modo manual (default): no debe aparecer "Pagar en línea".
        $this->actingAs($me)
            ->get('/client/facturas')
            ->assertOk()
            ->assertSee('MANUAL-001')
            ->assertDontSee('Pagar en línea');
    }

    public function test_pay_online_button_shows_in_automatic_mode(): void
    {
        \App\Domain\Notifications\Models\Setting::put(
            \App\Support\OperationMode::KEY_PAYMENTS,
            \App\Support\OperationMode::MODE_AUTOMATIC,
        );

        $me = $this->clientUser();
        Invoice::factory()->create([
            'client_id' => $me->client->id,
            'folio' => 'AUTO-001',
            'status' => InvoiceStatus::Pending->value,
        ]);

        $this->actingAs($me)
            ->get('/client/facturas')
            ->assertOk()
            ->assertSee('Pagar en línea');
    }
}
