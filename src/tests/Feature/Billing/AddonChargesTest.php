<?php

namespace Tests\Feature\Billing;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Services\PaymentOrderService;
use App\Domain\Billing\Services\PricingService;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\AddonCatalogItem;
use App\Domain\Services\Models\Service;
use App\Livewire\Admin\Services\AddonCatalogIndex;
use App\Livewire\Admin\Services\ServiceShow;
use App\Livewire\Client\Services\ServiceList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Complementos: IVA propio (pago único y recurrente), orden de pago al
 * agregarlos desde el admin y visibilidad para el cliente solo al pagarse.
 */
class AddonChargesTest extends TestCase
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

    private function clientWithService(): array
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        $client = Client::factory()->create(['user_id' => $user->id]);

        $service = Service::factory()->create([
            'client_id' => $client->id,
            'name' => 'Hosting Pro',
            'unit_price_cents' => 100000,
            'subtotal_cents' => 100000,
            'discount_cents' => 0,
            'tax_cents' => 16000,
            'tax_rate' => 16,
            'total_cents' => 116000,
            'currency' => 'MXN',
            'expires_at' => now()->addMonths(10)->toDateString(),
        ]);

        return [$user->refresh(), $service];
    }

    private function oneTimeItem(string $name, int $cents, float $tax): AddonCatalogItem
    {
        return AddonCatalogItem::create([
            'name' => $name, 'billing_mode' => 'one_time', 'price_cents' => $cents,
            'tax_rate' => $tax, 'currency' => 'MXN', 'is_active' => true,
        ]);
    }

    private function addFromCatalog(Service $service, AddonCatalogItem $item): void
    {
        Livewire::actingAs($this->admin())
            ->test(ServiceShow::class, ['service' => $service])
            ->set('addonMode', 'catalog')
            ->set('addonCatalogId', (string) $item->id)
            ->set('addonStatus', 'active')
            ->call('addAddon')
            ->assertHasNoErrors();
    }

    public function test_one_time_catalog_addon_saves_its_tax_rate(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AddonCatalogIndex::class)
            ->set('name', 'Migración')
            ->set('billingMode', 'one_time')
            ->set('oneTimePrice', '300')
            ->set('oneTimeTaxRate', '8')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(8.0, (float) AddonCatalogItem::where('name', 'Migración')->value('tax_rate'));
    }

    public function test_one_time_tax_rate_is_validated(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AddonCatalogIndex::class)
            ->set('name', 'IVA malo')
            ->set('billingMode', 'one_time')
            ->set('oneTimePrice', '300')
            ->set('oneTimeTaxRate', '-1')
            ->call('save')
            ->assertHasErrors('oneTimeTaxRate');
    }

    public function test_pricing_applies_each_addon_tax_rate(): void
    {
        [, $service] = $this->clientWithService();
        Addon::factory()->create([
            'service_id' => $service->id, 'status' => 'active', 'price_cents' => 50000, 'tax_rate' => 0,
            'billing_cycle' => null,
        ]);

        $b = app(PricingService::class)->breakdownFor($service->refresh()->load('addons'));

        // Servicio 1000 al 16% + complemento 500 al 0%.
        $this->assertSame(150000, $b['subtotal_cents']);
        $this->assertSame(16000, $b['tax_cents']);
        $this->assertSame(166000, $b['total_cents']);
    }

    public function test_addon_on_paid_service_gets_its_own_order_and_is_hidden_until_paid(): void
    {
        [$clientUser, $service] = $this->clientWithService();
        $orders = app(PaymentOrderService::class);
        $orders->markPaid($orders->createForService($service));
        $this->assertTrue($service->refresh()->isLockedByPayment());

        $item = $this->oneTimeItem('IP dedicada', 50000, 16);
        $this->addFromCatalog($service, $item);

        $addonOrder = PaymentOrder::where('service_id', $service->id)
            ->where('kind', PaymentOrder::KIND_ADDONS)->firstOrFail();

        // Solo se cobra el complemento (500 + 16%), no otra vez el servicio.
        $this->assertSame(PaymentOrderStatus::Pending, $addonOrder->status);
        $this->assertSame(58000, $addonOrder->amount_cents);
        $this->assertCount(1, $addonOrder->items);
        // El pedido sigue pagado/bloqueado.
        $this->assertTrue($service->refresh()->isLockedByPayment());

        // El cliente aún no lo ve.
        Livewire::actingAs($clientUser)->test(ServiceList::class)
            ->assertSee('Hosting Pro')
            ->assertDontSee('IP dedicada');

        $orders->markPaid($addonOrder->refresh());

        $addon = Addon::where('service_id', $service->id)->firstOrFail();
        $this->assertNotNull($addon->paid_at);
        // Su factura sale de las líneas de la orden (solo el complemento).
        $this->assertSame(58000, $addonOrder->refresh()->invoice->total_cents);

        Livewire::actingAs($clientUser)->test(ServiceList::class)->assertSee('IP dedicada');

        // Un complemento de pago único ya pagado no se vuelve a cobrar.
        $this->assertSame(116000, $service->refresh()->total_cents);
    }

    public function test_addon_on_unpaid_service_regenerates_the_pending_order(): void
    {
        [, $service] = $this->clientWithService();
        $first = app(PaymentOrderService::class)->createForService($service);

        $this->addFromCatalog($service, $this->oneTimeItem('Respaldo', 20000, 16));

        $this->assertSame(PaymentOrderStatus::Cancelled, $first->refresh()->status);

        $pending = PaymentOrder::where('service_id', $service->id)
            ->where('status', PaymentOrderStatus::Pending->value)->get();

        $this->assertCount(1, $pending);
        $this->assertSame(PaymentOrder::KIND_SERVICE, $pending->first()->kind);
        // (1000 + 200) + 16%.
        $this->assertSame(139200, $pending->first()->amount_cents);
        $this->assertNotNull($pending->first()->items->firstWhere('addon_id', '!=', null));
    }

    public function test_removing_the_unpaid_addon_cancels_its_order(): void
    {
        [, $service] = $this->clientWithService();
        $orders = app(PaymentOrderService::class);
        $orders->markPaid($orders->createForService($service));

        $this->addFromCatalog($service, $this->oneTimeItem('Certificado', 30000, 16));
        $addon = Addon::where('service_id', $service->id)->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(ServiceShow::class, ['service' => $service->refresh()])
            ->call('deleteAddon', $addon->id);

        $this->assertFalse(PaymentOrder::where('service_id', $service->id)
            ->where('kind', PaymentOrder::KIND_ADDONS)
            ->where('status', PaymentOrderStatus::Pending->value)
            ->exists());
    }
}
