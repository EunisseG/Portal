<?php

namespace Tests\Feature\Billing;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Services\PaymentReceiptData;
use App\Domain\Clients\Models\Client;
use App\Livewire\Admin\System\BrandingSettings;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El IVA del recibo sale de la orden (que lo copia del producto), no de una
 * configuración general en Apariencia.
 */
class ReceiptTaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function order(int $total, int $subtotal, int $tax, float $rate): PaymentOrder
    {
        return PaymentOrder::create([
            'folio' => 'PO-TAX-'.uniqid(),
            'client_id' => Client::factory()->create()->id,
            'amount_cents' => $total,
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'tax_rate' => $rate,
            'currency' => 'MXN',
            'status' => 'pending',
        ]);
    }

    public function test_receipt_uses_the_tax_rate_stored_in_the_order(): void
    {
        $data = app(PaymentReceiptData::class)->build($this->order(116000, 100000, 16000, 16));

        $this->assertSame('16', $data['taxRatePercent']);
        $this->assertSame(16000, $data['tax']->amountInCents);
        $this->assertSame(100000, $data['subtotal']->amountInCents);
    }

    public function test_receipt_shows_zero_percent_for_tax_exempt_product(): void
    {
        // Producto sin IVA: el recibo no debe mostrar 16% ni inventar impuesto.
        $data = app(PaymentReceiptData::class)->build($this->order(50000, 50000, 0, 0));

        $this->assertSame('0', $data['taxRatePercent']);
        $this->assertSame(0, $data['tax']->amountInCents);
        $this->assertSame(50000, $data['subtotal']->amountInCents);
    }

    public function test_receipt_keeps_decimal_rates(): void
    {
        $data = app(PaymentReceiptData::class)->build($this->order(10800, 10000, 800, 8));
        $this->assertSame('8', $data['taxRatePercent']);

        $data = app(PaymentReceiptData::class)->build($this->order(10850, 10000, 850, 8.5));
        $this->assertSame('8.5', $data['taxRatePercent']);
    }

    public function test_order_without_breakdown_does_not_invent_tax(): void
    {
        $data = app(PaymentReceiptData::class)->build($this->order(100000, 0, 0, 0));

        $this->assertSame(100000, $data['subtotal']->amountInCents);
        $this->assertSame(0, $data['tax']->amountInCents);
    }

    public function test_receipt_screen_only_shows_purchase_details(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        $client = Client::factory()->create(['user_id' => $user->id]);
        $order = $this->order(116000, 100000, 16000, 16);
        $order->update(['client_id' => $client->id]);

        Livewire::actingAs($user->refresh())
            ->test(\App\Livewire\Client\Invoices\PaymentOrderReceipt::class, ['order' => $order])
            ->assertSee('Detalles de tu compra')
            ->assertSee('Sub Total')
            ->assertDontSee('Detalles de la orden')
            ->assertDontSee('transacciones registradas')
            ->assertDontSee('Saldo');
    }

    public function test_receipt_pdf_downloads_without_order_details_section(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $order = $this->order(116000, 100000, 16000, 16);

        $response = $this->actingAs($admin)->get(route('admin.payment-orders.receipt.pdf', $order));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_appearance_page_no_longer_has_a_tax_rate_field(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(BrandingSettings::class)
            ->assertDontSee('Tasa de IVA')
            ->assertSee('Razón social');
    }
}
