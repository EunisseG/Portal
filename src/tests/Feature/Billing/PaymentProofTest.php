<?php

namespace Tests\Feature\Billing;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Enums\PaymentProofStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentProof;
use App\Domain\Clients\Models\Client;
use App\Livewire\Admin\Billing\PaymentOrderIndex;
use App\Livewire\Client\Invoices\InvoiceList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    public function test_client_can_submit_a_payment_proof(): void
    {
        $me = $this->clientUser();
        $invoice = Invoice::factory()->create(['client_id' => $me->client->id]);

        Livewire::actingAs($me)
            ->test(InvoiceList::class)
            ->set('tab', 'proofs')
            ->set('proofFile', UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'))
            ->set('proofAmount', '1160.00')
            ->set('proofMethod', 'transfer')
            ->set('proofReference', 'REF-123')
            ->set('proofInvoiceId', (string) $invoice->id)
            ->call('submitProof')
            ->assertHasNoErrors();

        $proof = PaymentProof::firstOrFail();
        $this->assertSame($me->client->id, $proof->client_id);
        $this->assertSame($invoice->id, $proof->invoice_id);
        $this->assertSame(116000, $proof->amount_cents);
        $this->assertSame(PaymentProofStatus::Pending, $proof->status);
        $this->assertSame('comprobante.pdf', $proof->original_name);
        Storage::disk('local')->assertExists($proof->file_path);
    }

    public function test_proof_upload_rejects_disallowed_file_types(): void
    {
        $me = $this->clientUser();

        Livewire::actingAs($me)
            ->test(InvoiceList::class)
            ->set('tab', 'proofs')
            ->set('proofFile', UploadedFile::fake()->create('malicioso.exe', 100, 'application/octet-stream'))
            ->set('proofAmount', '100.00')
            ->set('proofMethod', 'transfer')
            ->call('submitProof')
            ->assertHasErrors(['proofFile']);

        $this->assertSame(0, PaymentProof::count());
    }

    public function test_client_cannot_link_proof_to_another_clients_invoice(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();
        $foreignInvoice = Invoice::factory()->create(['client_id' => $other->client->id]);

        Livewire::actingAs($me)
            ->test(InvoiceList::class)
            ->set('tab', 'proofs')
            ->set('proofFile', UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'))
            ->set('proofAmount', '100.00')
            ->set('proofMethod', 'transfer')
            ->set('proofInvoiceId', (string) $foreignInvoice->id)
            ->call('submitProof')
            ->assertHasErrors(['proofInvoiceId']);

        $this->assertSame(0, PaymentProof::count());
    }

    public function test_client_sees_only_their_own_proofs(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();

        $myInvoice = Invoice::factory()->create(['client_id' => $me->client->id, 'folio' => 'MIA-PROOF-01']);
        $theirInvoice = Invoice::factory()->create(['client_id' => $other->client->id, 'folio' => 'AJENA-PROOF-99']);

        PaymentProof::create([
            'client_id' => $me->client->id,
            'invoice_id' => $myInvoice->id,
            'file_path' => 'payment-proofs/'.$me->client->id.'/mine.pdf',
            'original_name' => 'mine.pdf',
            'amount_cents' => 10000,
            'currency' => 'MXN',
            'method' => 'transfer',
            'reference' => 'MIREF-001',
            'status' => PaymentProofStatus::Pending->value,
        ]);
        PaymentProof::create([
            'client_id' => $other->client->id,
            'invoice_id' => $theirInvoice->id,
            'file_path' => 'payment-proofs/'.$other->client->id.'/theirs.pdf',
            'original_name' => 'theirs.pdf',
            'amount_cents' => 20000,
            'currency' => 'MXN',
            'method' => 'transfer',
            'reference' => 'AJENA-999',
            'status' => PaymentProofStatus::Pending->value,
        ]);

        $this->actingAs($me)
            ->get('/client/comprobantes')
            ->assertOk()
            ->assertSee('MIA-PROOF-01')
            ->assertDontSee('AJENA-PROOF-99');
    }

    public function test_admin_can_approve_a_proof(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $proof = $this->makeProof($client);

        Livewire::actingAs($admin)
            ->test(PaymentOrderIndex::class)
            ->call('approveProof', $proof->id)
            ->assertHasNoErrors();

        $proof->refresh();
        $this->assertSame(PaymentProofStatus::Approved, $proof->status);
        $this->assertSame($admin->id, $proof->reviewed_by);
        $this->assertNotNull($proof->reviewed_at);
    }

    public function test_admin_can_reject_a_proof_with_a_note(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $proof = $this->makeProof($client);

        Livewire::actingAs($admin)
            ->test(PaymentOrderIndex::class)
            ->call('startReject', $proof->id)
            ->set('rejectNote', 'El monto no coincide con la factura.')
            ->call('rejectProof')
            ->assertHasNoErrors();

        $proof->refresh();
        $this->assertSame(PaymentProofStatus::Rejected, $proof->status);
        $this->assertSame('El monto no coincide con la factura.', $proof->review_note);
        $this->assertSame($admin->id, $proof->reviewed_by);
    }

    public function test_reject_requires_a_note(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $proof = $this->makeProof($client);

        Livewire::actingAs($admin)
            ->test(PaymentOrderIndex::class)
            ->call('startReject', $proof->id)
            ->set('rejectNote', '')
            ->call('rejectProof')
            ->assertHasErrors(['rejectNote']);

        $this->assertSame(PaymentProofStatus::Pending, $proof->fresh()->status);
    }

    public function test_staff_can_manage_proofs(): void
    {
        $staff = $this->staff();
        $client = Client::factory()->create();
        $proof = $this->makeProof($client);

        Livewire::actingAs($staff)
            ->test(PaymentOrderIndex::class)
            ->call('approveProof', $proof->id)
            ->assertHasNoErrors();

        $this->assertSame(PaymentProofStatus::Approved, $proof->fresh()->status);
    }

    public function test_client_cannot_access_admin_invoices(): void
    {
        $me = $this->clientUser();

        $this->actingAs($me)
            ->get('/admin/facturas')
            ->assertForbidden();
    }

    public function test_owner_can_download_proof_but_other_client_cannot(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();
        $proof = $this->makeProof($me->client);

        $this->actingAs($me)
            ->get(route('client.payment-proofs.download', $proof))
            ->assertOk();

        $this->actingAs($other)
            ->get(route('client.payment-proofs.download', $proof))
            ->assertForbidden();
    }

    public function test_staff_can_download_any_proof(): void
    {
        $staff = $this->staff();
        $client = Client::factory()->create();
        $proof = $this->makeProof($client);

        $this->actingAs($staff)
            ->get(route('admin.payment-proofs.download', $proof))
            ->assertOk();
    }

    private function makeProof(Client $client): PaymentProof
    {
        $path = 'payment-proofs/'.$client->id.'/proof.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 demo');

        return PaymentProof::create([
            'client_id' => $client->id,
            'file_path' => $path,
            'original_name' => 'proof.pdf',
            'amount_cents' => 116000,
            'currency' => 'MXN',
            'method' => 'transfer',
            'status' => PaymentProofStatus::Pending->value,
        ]);
    }
}
