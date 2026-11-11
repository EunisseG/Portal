<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Server;
use App\Domain\Services\Models\Service;
use App\Livewire\Admin\Services\ServiceForm;
use App\Livewire\Admin\Services\ServiceIndex;
use App\Livewire\Admin\Services\ServiceShow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
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

    public function test_admin_can_create_a_service_for_a_client(): void
    {
        $client = Client::factory()->create();
        $server = Server::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('server_id', (string) $server->id)
            ->set('type', ServiceType::Hosting->value)
            ->set('name', 'Hosting Empresarial')
            ->set('status', ServiceStatus::Active->value)
            ->set('billing_cycle', BillingCycle::Annual->value)
            ->set('unit_price', '1499.00')
            ->set('tax_rate', '16')
            // Hosting siempre lleva dominio: uno nuevo que el cliente ya tiene (sin costo).
            ->set('domainMode', 'new')
            ->set('domainName', 'empresa.com')
            ->set('domainOrigin', 'owned')
            ->call('save')
            ->assertRedirect(route('admin.services.index'));

        $service = Service::where('name', 'Hosting Empresarial')->firstOrFail();
        $this->assertSame($client->id, $service->client_id);
        $this->assertSame($server->id, $service->server_id);
        $this->assertSame(149900, $service->unit_price_cents);
        // total = 1499 + 16% = 1738.84 => 173884 centavos
        $this->assertSame(173884, $service->total_cents);

        $this->assertDatabaseHas('audits', ['action' => AuditAction::ServiceCreated->value]);
    }

    public function test_service_requires_a_client(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('client_id', '')
            ->set('name', 'Sin cliente')
            ->set('unit_price', '100')
            ->call('save')
            ->assertHasErrors(['client_id']);
    }

    public function test_admin_can_add_and_remove_addon_from_service(): void
    {
        $service = Service::factory()->create();

        $component = Livewire::actingAs($this->admin())
            ->test(ServiceShow::class, ['service' => $service])
            ->set('addonMode', 'custom')
            ->set('addonName', 'IP dedicada')
            ->set('addonStatus', 'active')
            ->set('addonPrice', '200')
            ->call('addAddon');

        $this->assertDatabaseHas('addons', ['name' => 'IP dedicada', 'service_id' => $service->id]);
        $this->assertDatabaseHas('audits', ['action' => AuditAction::AddonCreated->value]);

        $addonId = $service->addons()->first()->id;

        $component->call('deleteAddon', $addonId);

        $this->assertDatabaseMissing('addons', ['id' => $addonId]);
        $this->assertDatabaseHas('audits', ['action' => AuditAction::AddonDeleted->value]);
    }

    public function test_billing_role_cannot_create_services(): void
    {
        $billing = User::factory()->create();
        $billing->assignRole(RoleName::Billing->value);

        // billing tiene services.view pero no services.manage.
        Livewire::actingAs($billing)
            ->test(ServiceForm::class)
            ->assertForbidden();
    }

    public function test_orders_kpis_count_by_status(): void
    {
        $client = Client::factory()->create();
        Service::factory()->create(['client_id' => $client->id, 'status' => ServiceStatus::Pending->value]);
        Service::factory()->count(2)->create(['client_id' => $client->id, 'status' => ServiceStatus::Active->value]);
        Service::factory()->create(['client_id' => $client->id, 'status' => ServiceStatus::Suspended->value]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Services\ServiceIndex::class)
            ->assertViewHas('kpis', fn ($kpis) => $kpis['pending'] === 1
                && $kpis['active'] === 2
                && $kpis['suspended'] === 1
                && $kpis['total'] === 4);
    }

    public function test_orders_status_chart_has_distribution_data(): void
    {
        $client = Client::factory()->create();
        Service::factory()->create(['client_id' => $client->id, 'status' => ServiceStatus::Pending->value]);
        Service::factory()->count(2)->create(['client_id' => $client->id, 'status' => ServiceStatus::Active->value]);
        Service::factory()->create(['client_id' => $client->id, 'status' => ServiceStatus::Cancelled->value]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Services\ServiceIndex::class)
            ->assertSee('Distribución de pedidos por estado')
            // chart['data'] = [activo, pendiente, suspendido, cancelado]
            ->assertViewHas('chart', fn ($chart) => $chart['data'] === [2, 1, 0, 1]
                && count($chart['labels']) === 4);
    }

    public function test_domains_kpis_and_chart_by_status(): void
    {
        $client = Client::factory()->create();
        \App\Domain\Services\Models\Domain::factory()->count(2)->create(['client_id' => $client->id, 'status' => \App\Domain\Services\Enums\DomainStatus::Active->value]);
        \App\Domain\Services\Models\Domain::factory()->create(['client_id' => $client->id, 'status' => \App\Domain\Services\Enums\DomainStatus::Expired->value]);
        \App\Domain\Services\Models\Domain::factory()->create(['client_id' => $client->id, 'status' => \App\Domain\Services\Enums\DomainStatus::Cancelled->value]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Services\ServiceIndex::class)
            ->set('tab', 'domains')
            ->assertSee('Distribución de dominios por estado')
            ->assertViewHas('domainKpis', fn ($k) => $k['active'] === 2
                && $k['expired'] === 1
                && $k['cancelled'] === 1
                && $k['total'] === 4)
            // domainChart['data'] = [activo, pendiente, vencido, transferencia, cancelado]
            ->assertViewHas('domainChart', fn ($c) => $c['data'] === [2, 0, 1, 0, 1]);
    }

    public function test_orders_kpi_filters_by_status(): void
    {
        $client = Client::factory()->create();
        Service::factory()->create(['client_id' => $client->id, 'status' => ServiceStatus::Active->value, 'name' => 'ACTIVO UNO']);
        Service::factory()->create(['client_id' => $client->id, 'status' => ServiceStatus::Suspended->value, 'name' => 'SUSPENDIDO UNO']);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Services\ServiceIndex::class)
            ->call('filterByStatus', 'active')
            ->assertSee('ACTIVO UNO')
            ->assertDontSee('SUSPENDIDO UNO');
    }

    public function test_server_block_only_for_hosting_type(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('type', ServiceType::Hosting->value)
            ->assertSee('Servidor (opcional)')
            ->set('type', ServiceType::Email->value)
            ->assertDontSee('Servidor (opcional)');
    }

    public function test_changing_type_to_non_hosting_clears_server(): void
    {
        $server = Server::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('type', ServiceType::Hosting->value)
            ->set('server_id', (string) $server->id)
            ->set('type', ServiceType::Email->value)
            ->assertSet('server_id', '');
    }

    public function test_category_lists_its_products_including_hosting_plans(): void
    {
        $hosting = \App\Domain\Services\Models\ProductCategory::create(['title' => 'Hosting']);
        $other = \App\Domain\Services\Models\ProductCategory::create(['title' => 'Licencias']);

        // Los planes de hosting llegan a Productos con su categoría.
        app(\App\Domain\Services\Services\PlanService::class)->create(
            ['name' => 'Hosting Pro', 'type' => 'hosting', 'category_id' => $hosting->id, 'is_active' => true],
            ['monthly' => ['unit_price' => '199', 'tax_rate' => '16']],
        );
        app(\App\Domain\Services\Services\PlanService::class)->create(
            ['name' => 'Hosting Idea', 'type' => 'hosting', 'category_id' => $hosting->id, 'is_active' => true],
            ['annual' => ['unit_price' => '999', 'tax_rate' => '16']],
        );
        \App\Domain\Services\Models\Product::create([
            'name' => 'Licencia X', 'type' => 'license', 'billing_mode' => 'one_time', 'category_id' => $other->id,
            'price_cents' => 50000, 'tax_rate' => 16, 'currency' => 'MXN', 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->assertSee('Categoría')
            ->set('category_id', (string) $hosting->id)
            ->assertSee('Hosting Pro')
            ->assertSee('Hosting Idea')
            ->assertDontSee('Licencia X');
    }

    public function test_choosing_a_product_fills_name_type_plan_and_pricing(): void
    {
        $category = \App\Domain\Services\Models\ProductCategory::create(['title' => 'Hosting']);
        $plan = app(\App\Domain\Services\Services\PlanService::class)->create(
            ['name' => 'Hosting Pro', 'type' => 'hosting', 'category_id' => $category->id, 'is_active' => true],
            ['annual' => ['unit_price' => '1999', 'tax_rate' => '16']],
        );
        $product = \App\Domain\Services\Models\Product::where('plan_id', $plan->id)->firstOrFail();

        $this->assertSame($category->id, $product->category_id);

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('type', ServiceType::Email->value)
            ->set('category_id', (string) $category->id)
            ->set('product_id', (string) $product->id)
            ->assertSet('name', 'Hosting Pro')
            ->assertSet('type', ServiceType::Hosting->value)
            ->assertSet('plan_id', (string) $plan->id)
            ->assertSet('billing_cycle', 'annual')
            ->assertSet('unit_price', '1999.00')
            ->assertSet('tax_rate', '16');
    }

    public function test_order_from_product_saves_product_and_selected_addons(): void
    {
        $client = Client::factory()->create();
        $product = \App\Domain\Services\Models\Product::create([
            'name' => 'Licencia Office', 'type' => 'license', 'billing_mode' => 'one_time',
            'price_cents' => 100000, 'tax_rate' => 16, 'currency' => 'MXN', 'is_active' => true,
        ]);
        $addon = \App\Domain\Services\Models\AddonCatalogItem::create([
            'name' => 'Soporte extra', 'billing_mode' => 'one_time', 'price_cents' => 20000,
            'currency' => 'MXN', 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('category_id', 'none')
            ->set('product_id', (string) $product->id)
            ->set('selectedAddons.'.$addon->id, true)
            ->assertSee('Complemento: Soporte extra')
            ->call('save')
            ->assertHasNoErrors();

        $service = Service::where('client_id', $client->id)->with('addons')->firstOrFail();
        $this->assertSame($product->id, $service->product_id);
        $this->assertSame('Licencia Office', $service->name);
        $this->assertCount(1, $service->addons);
        $this->assertSame($addon->id, $service->addons->first()->catalog_item_id);
        $this->assertSame(20000, $service->addons->first()->price_cents);
        // (1000 + 200) + 16% IVA.
        $this->assertSame(139200, $service->total_cents);
    }

    public function test_domain_extension_from_products_sets_name_and_price(): void
    {
        $client = Client::factory()->create();
        $mx = \App\Domain\Services\Models\Product::create([
            'name' => '.mx', 'type' => 'domain', 'billing_mode' => 'one_time',
            'price_cents' => 45000, 'tax_rate' => 16, 'currency' => 'MXN', 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('product_id', 'manual')
            ->set('type', ServiceType::Hosting->value)
            ->set('name', 'Hosting manual')
            ->set('unit_price', '100')
            ->set('domainMode', 'new')
            ->set('domainName', 'midominio')
            ->set('domainOrigin', 'purchase')
            ->assertSee('.mx')
            ->set('domainTldId', (string) $mx->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('domains', ['client_id' => $client->id, 'name' => 'midominio.mx']);
        $this->assertDatabaseHas('addons', ['is_domain' => true, 'name' => 'Dominio (midominio.mx)', 'price_cents' => 45000]);
    }

    public function test_client_search_filters_the_list(): void
    {
        $ana = Client::factory()->create(['company' => 'Empresa Ana']);
        // Emails fijos: el filtro busca también por correo, así que un email
        // aleatorio con "ana" (p. ej. "juana@...") haría el test intermitente.
        $ana->user->update(['name' => 'Ana Pérez', 'email' => 'ana.perez@wortev.test']);
        $beto = Client::factory()->create(['company' => 'Empresa Beto']);
        $beto->user->update(['name' => 'Beto López', 'email' => 'beto.lopez@wortev.test']);

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('clientSearch', 'Pérez')
            ->assertSee('Ana Pérez')
            ->assertDontSee('Beto López');
    }

    public function test_autocomplete_selects_client(): void
    {
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('clientSearch', 'x')
            ->call('selectClient', $client->id)
            ->assertSet('client_id', (string) $client->id)
            ->assertSet('clientSearch', ''); // se limpia la búsqueda al seleccionar
    }

    public function test_manual_mode_shows_type_and_free_name(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->assertDontSee('Otro tipo de servicio')
            ->set('product_id', 'manual')
            ->assertSee('Tipo')
            ->assertSeeHtml('id="order-name"');
    }

    public function test_domain_block_only_for_hosting(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('type', ServiceType::Hosting->value)
            ->assertSee('Ligar dominio existente')
            ->set('type', ServiceType::Email->value)
            ->assertDontSee('Ligar dominio existente');
    }

    public function test_hosting_order_links_existing_domain(): void
    {
        $client = Client::factory()->create();
        $domain = \App\Domain\Services\Models\Domain::create([
            'client_id' => $client->id,
            'name' => 'existente.com',
            'status' => \App\Domain\Services\Enums\DomainStatus::Active->value,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('type', ServiceType::Hosting->value)
            ->set('name', 'Hosting con dominio')
            ->set('status', ServiceStatus::Active->value)
            ->set('billing_cycle', BillingCycle::Annual->value)
            ->set('unit_price', '1000')
            ->set('tax_rate', '16')
            ->set('domainMode', 'existing')
            ->set('domainId', (string) $domain->id)
            ->call('save');

        $service = Service::where('name', 'Hosting con dominio')->firstOrFail();
        $this->assertSame($service->id, $domain->fresh()->service_id);
    }

    public function test_hosting_order_creates_new_domain(): void
    {
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('type', ServiceType::Hosting->value)
            ->set('name', 'Hosting nuevo dominio')
            ->set('status', ServiceStatus::Active->value)
            ->set('billing_cycle', BillingCycle::Annual->value)
            ->set('unit_price', '1000')
            ->set('tax_rate', '16')
            ->set('domainMode', 'new')
            ->set('domainName', 'nuevodominio.com')
            ->call('save');

        $service = Service::where('name', 'Hosting nuevo dominio')->firstOrFail();
        $this->assertDatabaseHas('domains', [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'name' => 'nuevodominio.com',
        ]);
    }

    public function test_client_list_shows_on_open_without_search(): void
    {
        $client = Client::factory()->create();
        $client->user->update(['name' => 'Cliente Visible']);

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->assertSee('Cliente Visible'); // aparece sin haber escrito nada
    }

    /** Crea un pedido hosting con dominio nuevo comprado; devuelve el total_cents. */
    private function createHostingWithPurchasedDomain(string $domain, bool $premium, string $price): int
    {
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('type', ServiceType::Hosting->value)
            ->set('name', 'Hosting '.$domain)
            ->set('status', ServiceStatus::Active->value)
            ->set('billing_cycle', BillingCycle::Annual->value)
            ->set('unit_price', '1000')
            ->set('tax_rate', '0') // sin IVA para aislar el costo del dominio
            ->set('domainMode', 'new')
            ->set('domainName', $domain)
            ->set('domainOrigin', 'purchase')
            ->set('domainIsPremium', $premium)
            ->set('domainPrice', $price)
            ->call('save');

        return Service::where('name', 'Hosting '.$domain)->firstOrFail()->total_cents;
    }

    public function test_com_domain_is_free(): void
    {
        // .com no premium => gratis; total = 1000 (sin IVA), dominio 0.
        $total = $this->createHostingWithPurchasedDomain('gratis.com', premium: false, price: '999');
        $this->assertSame(100000, $total);
    }

    public function test_com_mx_domain_is_free(): void
    {
        $total = $this->createHostingWithPurchasedDomain('gratis.com.mx', premium: false, price: '999');
        $this->assertSame(100000, $total);
    }

    public function test_premium_com_domain_adds_cost(): void
    {
        // .com pero premium => cobra el precio capturado (500) y se suma al total.
        $total = $this->createHostingWithPurchasedDomain('premium.com', premium: true, price: '500');
        $this->assertSame(150000, $total); // 1000 + 500
    }

    public function test_other_tld_adds_cost(): void
    {
        // Extensión distinta de .com/.com.mx => cobra el precio capturado.
        $total = $this->createHostingWithPurchasedDomain('mitienda.mx', premium: false, price: '350');
        $this->assertSame(135000, $total); // 1000 + 350
    }

    public function test_domain_is_a_taxed_concept_in_breakdown(): void
    {
        // Escenario: servicio 1999 + dominio premium 25254, ambos con IVA 16%.
        // Servicio: 1999 + 16% = 2318.84 ; Dominio: 25254 + 16% = 29294.64
        // Total: 31613.48 => 3161348 centavos.
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('type', ServiceType::Hosting->value)
            ->set('name', 'Hosting Pro')
            ->set('status', ServiceStatus::Active->value)
            ->set('billing_cycle', BillingCycle::Annual->value)
            ->set('unit_price', '1999')
            ->set('tax_rate', '16')
            ->set('domainMode', 'new')
            ->set('domainName', 'gloria.com')
            ->set('domainOrigin', 'purchase')
            ->set('domainIsPremium', true)
            ->set('domainPrice', '25254')
            ->call('save');

        $service = Service::where('name', 'Hosting Pro')->firstOrFail();

        // El precio unitario del pedido es SOLO el servicio (el dominio va aparte).
        $this->assertSame(199900, $service->unit_price_cents);
        // El dominio quedó como complemento propio del pedido.
        $domainAddon = $service->addons()->where('is_domain', true)->firstOrFail();
        $this->assertSame(2525400, $domainAddon->price_cents);
        // Subtotal = 1999 + 25254 = 27253 ; IVA 16% = 4360.48 ; total = 31613.48
        $this->assertSame(2725300, $service->subtotal_cents);
        $this->assertSame(436048, $service->tax_cents);
        $this->assertSame(3161348, $service->total_cents);
    }

    public function test_owned_domain_has_no_cost(): void
    {
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ServiceForm::class)
            ->set('client_id', (string) $client->id)
            ->set('type', ServiceType::Hosting->value)
            ->set('name', 'Hosting propio')
            ->set('status', ServiceStatus::Active->value)
            ->set('billing_cycle', BillingCycle::Annual->value)
            ->set('unit_price', '1000')
            ->set('tax_rate', '0')
            ->set('domainMode', 'new')
            ->set('domainName', 'yatengo.net') // aunque no sea .com, al ser 'owned' no cobra
            ->set('domainOrigin', 'owned')
            ->call('save');

        $this->assertSame(100000, Service::where('name', 'Hosting propio')->firstOrFail()->total_cents);
    }

    // --- Pestaña Dominios dentro de Pedidos ---

    public function test_service_type_filter_narrows_list(): void
    {
        $client = Client::factory()->create();
        Service::factory()->create(['client_id' => $client->id, 'type' => ServiceType::Hosting->value, 'name' => 'MI HOSTING']);
        Service::factory()->create(['client_id' => $client->id, 'type' => ServiceType::Email->value, 'name' => 'MI CORREO']);

        Livewire::actingAs($this->admin())
            ->test(ServiceIndex::class)
            ->set('typeFilter', ServiceType::Hosting->value)
            ->assertSee('MI HOSTING')
            ->assertDontSee('MI CORREO');
    }

    public function test_domains_tab_lists_and_filters_by_status(): void
    {
        $client = Client::factory()->create();
        \App\Domain\Services\Models\Domain::create([
            'client_id' => $client->id,
            'name' => 'activo.com',
            'status' => \App\Domain\Services\Enums\DomainStatus::Active->value,
        ]);
        \App\Domain\Services\Models\Domain::create([
            'client_id' => $client->id,
            'name' => 'vencido.com',
            'status' => \App\Domain\Services\Enums\DomainStatus::Expired->value,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ServiceIndex::class)
            ->call('setTab', 'domains')
            ->assertSee('activo.com')
            ->assertSee('vencido.com')
            ->set('statusFilter', \App\Domain\Services\Enums\DomainStatus::Active->value)
            ->assertSee('activo.com')
            ->assertDontSee('vencido.com');
    }

    public function test_export_filters_by_type(): void
    {
        $client = Client::factory()->create();
        Service::factory()->create(['client_id' => $client->id, 'type' => ServiceType::Hosting->value]);
        Service::factory()->create(['client_id' => $client->id, 'type' => ServiceType::Email->value]);

        $export = new \App\Exports\ServicesExport(ServiceType::Hosting->value, null);
        $count = $export->query()->count();

        $this->assertSame(1, $count);
    }

    public function test_export_filters_by_client(): void
    {
        $a = Client::factory()->create();
        $b = Client::factory()->create();
        Service::factory()->count(2)->create(['client_id' => $a->id]);
        Service::factory()->create(['client_id' => $b->id]);

        $export = new \App\Exports\ServicesExport(null, $a->id);

        $this->assertSame(2, $export->query()->count());
    }
}
