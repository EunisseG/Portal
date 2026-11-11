<?php

namespace App\Livewire\Admin\Services;

use App\Domain\Billing\Services\PricingService;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\AddonCatalogItem;
use App\Domain\Services\Models\Promotion;
use App\Domain\Services\Models\Service;
use App\Domain\Services\Services\AddonService;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Integrations\Providers\Whm\HostingProviderFactory;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;

class ServiceShow extends Component
{
    public Service $service;

    // Formulario inline de complemento.
    public bool $showAddonForm = false;
    /** Modo de alta del complemento: 'catalog' (elegir del catálogo) o 'custom' (a mano). */
    public string $addonMode = 'catalog';
    public string $addonCatalogId = '';
    public string $addonName = '';
    public string $addonStatus = 'active';
    public string $addonPrice = '';
    /** Ciclo elegido para un complemento recurrente del catálogo. */
    public string $addonCycle = '';
    public string $addonExpiresAt = '';

    // Promoción / descuento aplicado al pedido.
    public bool $showPromotionForm = false;
    public string $promotionId = '';

    /** Buzones cargados bajo demanda (null = aún no se consultó). */
    public ?array $mailboxes = null;

    // Vinculación manual de una cuenta cPanel existente.
    public bool $showLinkForm = false;
    public string $linkUsername = '';

    public function mount(Service $service): void
    {
        Gate::authorize('view', Service::class);
        $this->service = $service->load(['client.user', 'server', 'addons', 'domains', 'promotion', 'invoices']);
        $this->promotionId = (string) ($service->promotion_id ?? '');
    }

    /** Consulta las cuentas de correo del servicio (solo hosting con panel). */
    public function loadMailboxes(\App\Domain\Services\Services\ServiceMailboxViewer $viewer, bool $fresh = false): void
    {
        Gate::authorize('view', Service::class);

        $this->mailboxes = $viewer->for($this->service, fresh: $fresh);
    }

    /** ¿Este servicio puede mostrar correos? (hosting con panel y cuenta) */
    public function canShowMailboxes(): bool
    {
        return $this->service->type === \App\Domain\Services\Enums\ServiceType::Hosting
            && (bool) $this->service->server?->hasPanel()
            && ! empty($this->service->whm_username);
    }

    /**
     * ¿Se puede vincular/gestionar la cuenta cPanel? (hosting con panel
     * configurado en su servidor). No exige que ya exista whm_username.
     */
    public function canLinkAccount(): bool
    {
        return $this->service->type === \App\Domain\Services\Enums\ServiceType::Hosting
            && (bool) $this->service->server?->hasPanel();
    }

    /**
     * Vincula el servicio a una cuenta cPanel YA existente en el servidor.
     * Verifica que la cuenta exista (accountsummary) antes de guardar el
     * usuario, para no dejar vínculos rotos.
     */
    public function linkAccount(RecordIntegrationLog $log): void
    {
        Gate::authorize('update', Service::class);

        $data = $this->validate([
            'linkUsername' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+$/i'],
        ], [
            'linkUsername.regex' => 'El usuario de cPanel solo puede contener letras y números.',
        ], [
            'linkUsername' => 'usuario de cPanel',
        ]);

        $server = $this->service->server;
        $hosting = $server ? HostingProviderFactory::forServer($server) : null;

        if (! $hosting) {
            session()->flash('error', 'El servidor de este servicio no tiene una conexión de hosting configurada.');

            return;
        }

        $username = $data['linkUsername'];

        // Verifica que la cuenta exista en el WHM del servidor.
        $result = $hosting->getAccountInfo($username);

        $log->record('whm.link_account', [
            'integration_id' => $this->whmIntegrationId(),
            'success' => $result->success,
            'message' => $result->success ? null : $result->message,
            'internal_reference' => 'Service:'.$this->service->id,
            'external_reference' => 'WhmAccount:'.$username.'@'.$server->name,
        ]);

        if (! $result->success) {
            session()->flash('error', 'No se pudo verificar la cuenta "'.$username.'" en el servidor: '.$result->message);

            return;
        }

        $this->service->update(['whm_username' => $username]);
        // Limpia el caché de buzones por si había datos de otra cuenta.
        \Illuminate\Support\Facades\Cache::forget('service-mailboxes:'.$this->service->id);

        $this->reset('linkUsername', 'showLinkForm');
        $this->service->refresh()->load(['client.user', 'server', 'addons', 'domains']);
        session()->flash('status', 'Servicio vinculado a la cuenta cPanel: '.$username);
    }

    /** Desvincula la cuenta cPanel del servicio (no borra nada en el servidor). */
    public function unlinkAccount(): void
    {
        Gate::authorize('update', Service::class);

        $this->service->update(['whm_username' => null]);
        \Illuminate\Support\Facades\Cache::forget('service-mailboxes:'.$this->service->id);
        $this->mailboxes = null;

        $this->service->refresh()->load(['client.user', 'server', 'addons', 'domains']);
        session()->flash('status', 'Cuenta cPanel desvinculada del servicio.');
    }

    /** Al elegir un ítem del catálogo, autocompleta nombre y precio. */
    public function updatedAddonCatalogId(): void
    {
        if ($this->addonCatalogId === '') {
            return;
        }

        $item = AddonCatalogItem::with('prices')->find($this->addonCatalogId);
        if (! $item) {
            return;
        }

        $this->addonName = $item->name;

        if ($item->isRecurring()) {
            // Preselecciona el primer ciclo disponible (mensual si existe) y
            // muestra su precio unitario (sin IVA; el IVA lo aplica el pedido).
            $price = $item->prices->firstWhere('billing_cycle', \App\Domain\Services\Enums\BillingCycle::Monthly)
                ?? $item->prices->first();
            $this->addonCycle = $price?->billing_cycle->value ?? '';
            $this->addonPrice = $price ? number_format($price->unit_price_cents / 100, 2, '.', '') : '';
        } else {
            $this->addonCycle = '';
            $this->addonPrice = number_format($item->price_cents / 100, 2, '.', '');
        }
    }

    /** Cuando cambia el ciclo elegido, refresca el precio mostrado. */
    public function updatedAddonCycle(): void
    {
        if ($this->addonCatalogId === '' || $this->addonCycle === '') {
            return;
        }

        $item = AddonCatalogItem::with('prices')->find($this->addonCatalogId);
        $price = $item?->prices->firstWhere('billing_cycle', $this->addonCycle);
        if ($price) {
            $this->addonPrice = number_format($price->unit_price_cents / 100, 2, '.', '');
        }
    }

    public function addAddon(AddonService $addons, PricingService $pricing): void
    {
        Gate::authorize('create', Addon::class);

        $billingCycle = null;
        // IVA del complemento; null = el del pedido (complemento personalizado).
        $taxRate = null;

        // Si viene del catálogo, tomamos nombre y precio del ítem elegido.
        if ($this->addonMode === 'catalog') {
            $data = $this->validate([
                'addonCatalogId' => ['required', Rule::exists('addon_catalog_items', 'id')],
                'addonStatus' => ['required', new Enum(AddonStatus::class)],
                'addonExpiresAt' => ['nullable', 'date'],
            ]);

            $item = AddonCatalogItem::with('prices')->findOrFail($data['addonCatalogId']);
            $name = $item->name;
            $catalogId = $item->id;

            if ($item->isRecurring()) {
                // Recurrente: el admin elige el ciclo; se congela el precio
                // unitario de ese ciclo y su IVA.
                $this->validate([
                    'addonCycle' => ['required', new Enum(\App\Domain\Services\Enums\BillingCycle::class)],
                ], [], ['addonCycle' => 'ciclo de facturación']);

                $price = $item->prices->firstWhere('billing_cycle', $this->addonCycle);
                if (! $price) {
                    $this->addError('addonCycle', 'Ese ciclo no tiene precio configurado para este complemento.');

                    return;
                }

                $priceCents = (int) $price->unit_price_cents;
                $taxRate = (float) $price->tax_rate;
                $billingCycle = $price->billing_cycle;
            } else {
                // Pago único: precio plano del catálogo con su IVA.
                $priceCents = (int) $item->price_cents;
                $taxRate = (float) ($item->tax_rate ?? 16);
            }
        } else {
            $data = $this->validate([
                'addonName' => ['required', 'string', 'max:255'],
                'addonStatus' => ['required', new Enum(AddonStatus::class)],
                'addonPrice' => ['required', 'numeric', 'min:0'],
                'addonExpiresAt' => ['nullable', 'date'],
            ]);
            $name = $data['addonName'];
            $priceCents = Money::fromDecimal($data['addonPrice'])->amountInCents;
            $catalogId = null;
        }

        // Vencimiento: si el admin lo indicó, se respeta. Si no y el complemento
        // es recurrente, se calcula a partir de su ciclo (hoy + N meses) para
        // que renueve/caduque por su cuenta.
        $expiresAt = $data['addonExpiresAt'] ?: null;
        if ($expiresAt === null && $billingCycle !== null) {
            $expiresAt = now()->addMonths($billingCycle->months())->toDateString();
        }

        $addons->create([
            'service_id' => $this->service->id,
            'catalog_item_id' => $catalogId,
            'name' => $name,
            'status' => $data['addonStatus'],
            'price_cents' => $priceCents,
            'tax_rate' => $taxRate,
            'billing_cycle' => $billingCycle?->value,
            'currency' => Money::DEFAULT_CURRENCY,
            'expires_at' => $expiresAt,
        ]);

        // El complemento afecta el total del pedido: recalcular y generar su
        // orden de pago (el cliente lo verá en sus servicios al pagarla).
        $pricing->recalculate($this->service->refresh()->load('addons'));
        $order = $this->syncAddonCharges();

        $this->reset(['addonCatalogId', 'addonName', 'addonPrice', 'addonCycle', 'addonExpiresAt', 'showAddonForm']);
        $this->addonStatus = 'active';
        $this->addonMode = 'catalog';
        $this->service->refresh()->load(['addons', 'promotion']);
        session()->flash('status', $order
            ? 'Complemento agregado. Orden de pago '.$order->folio.' generada; el cliente lo verá al pagarla.'
            : 'Complemento agregado.');
    }

    /**
     * Genera (o regenera) la orden de pago por los complementos. Un fallo de la
     * pasarela no debe impedir el cambio: se avisa pero no se interrumpe.
     */
    private function syncAddonCharges(): ?\App\Domain\Billing\Models\PaymentOrder
    {
        try {
            return app(\App\Domain\Billing\Services\PaymentOrderService::class)
                ->syncAddonCharges($this->service, app(\App\Integrations\Contracts\PaymentGateway::class));
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'No se pudo generar la orden de pago del complemento: '.$e->getMessage());

            return null;
        }
    }

    public function deleteAddon(int $addonId, AddonService $addons, PricingService $pricing): void
    {
        Gate::authorize('delete', Addon::class);

        $addon = Addon::where('service_id', $this->service->id)->findOrFail($addonId);
        $wasPaid = $addon->isPaid();
        $addons->delete($addon);

        $pricing->recalculate($this->service->refresh()->load('addons'));
        // Si aún no se pagaba, la orden pendiente se rehace sin él.
        if (! $wasPaid) {
            $this->syncAddonCharges();
        }
        $this->service->refresh()->load(['addons', 'promotion']);
        session()->flash('status', 'Complemento eliminado.');
    }

    /**
     * Renueva un complemento recurrente: avanza su vencimiento un ciclo y lo
     * reactiva. Al reactivarlo vuelve a contar en el total del pedido.
     */
    public function renewAddon(int $addonId, PricingService $pricing): void
    {
        Gate::authorize('update', Service::class);

        $addon = Addon::where('service_id', $this->service->id)->findOrFail($addonId);

        if (! $addon->renew()) {
            session()->flash('error', 'Solo los complementos recurrentes se pueden renovar.');

            return;
        }

        $pricing->recalculate($this->service->refresh()->load('addons'));
        $this->service->refresh()->load(['addons', 'promotion']);

        // Registra el evento comercial de renovación para el ejecutivo del cliente.
        app(\App\Domain\Sales\Services\SalesEventRecorder::class)->recordRenewal($addon->fresh());

        session()->flash('status', 'Complemento renovado hasta '.$addon->fresh()->expires_at->format('Y-m-d').'.');
    }

    /** Aplica una promoción vigente al pedido y recalcula el total. */
    public function applyPromotion(PricingService $pricing): void
    {
        Gate::authorize('update', Service::class);

        $this->validate([
            'promotionId' => ['required', Rule::exists('promotions', 'id')],
        ], [], ['promotionId' => 'promoción']);

        $promotion = Promotion::findOrFail($this->promotionId);

        if (! $pricing->applyPromotion($this->service->load('addons'), $promotion)) {
            $this->addError('promotionId', 'La promoción no está vigente o está inactiva.');

            return;
        }

        $this->service->refresh()->load(['addons', 'promotion']);
        $this->showPromotionForm = false;
        session()->flash('status', 'Promoción aplicada: '.$promotion->name.'.');
    }

    /** Quita la promoción del pedido y recalcula. */
    public function removePromotion(PricingService $pricing): void
    {
        Gate::authorize('update', Service::class);

        $pricing->removePromotion($this->service->load('addons'));

        $this->promotionId = '';
        $this->service->refresh()->load(['addons', 'promotion']);
        session()->flash('status', 'Promoción retirada.');
    }

    /**
     * Genera una factura a partir de este pedido, con sus líneas (servicio,
     * complementos y descuento). Requiere permiso de gestión de facturas.
     */
    public function generateInvoice(\App\Domain\Billing\Services\InvoiceService $invoices): void
    {
        Gate::authorize('create', \App\Domain\Billing\Models\Invoice::class);

        $invoice = $invoices->createFromService($this->service->load(['addons', 'plan', 'promotion']));

        $this->service->refresh()->load(['addons', 'promotion', 'invoices']);
        session()->flash('status', 'Factura generada desde el pedido (folio pendiente). Total: '.$invoice->total()->format().'.');
    }

    /**
     * Crea la cuenta de hosting en el WHM del servidor de este servicio
     * (acción manual). Guarda el usuario WHM en el servicio y registra el log.
     */
    public function whmCreateAccount(RecordIntegrationLog $log): void
    {
        Gate::authorize('update', Service::class);

        $server = $this->service->server;
        $hosting = $server ? HostingProviderFactory::forServer($server) : null;
        if (! $hosting) {
            session()->flash('error', 'El servidor de este servicio no tiene una conexión de hosting configurada.');

            return;
        }

        $client = $this->service->client;
        $username = $this->suggestUsername();
        $domain = $this->service->domains->first()?->name ?? ($username.'.example.com');

        $result = $hosting->createAccount([
            'domain' => $domain,
            'username' => $username,
            'plan' => $this->service->name,
            'contactemail' => $client?->user?->email,
        ]);

        if ($result->success) {
            $account = $result->data['username'] ?? $result->data['external_id'] ?? $username;

            $this->service->update(['whm_username' => $account]);

            $log->record('whm.createacct', [
                'integration_id' => $this->whmIntegrationId(),
                'success' => true,
                'internal_reference' => 'Service:'.$this->service->id,
                'external_reference' => 'WhmAccount:'.$account.'@'.$server->name,
            ]);

            session()->flash('status', 'Cuenta de hosting creada: '.$account);
        } else {
            $log->record('whm.createacct', [
                'integration_id' => $this->whmIntegrationId(),
                'success' => false,
                'message' => $result->message,
                'internal_reference' => 'Service:'.$this->service->id,
            ]);
            session()->flash('error', 'No se pudo crear la cuenta: '.$result->message);
        }

        $this->service->refresh()->load(['client.user', 'server', 'addons', 'domains']);
    }

    public function whmSuspend(RecordIntegrationLog $log): void
    {
        $this->whmChangeState($log, suspend: true);
    }

    public function whmUnsuspend(RecordIntegrationLog $log): void
    {
        $this->whmChangeState($log, suspend: false);
    }

    private function whmChangeState(RecordIntegrationLog $log, bool $suspend): void
    {
        Gate::authorize('update', Service::class);

        $server = $this->service->server;
        $username = $this->service->whm_username;
        $hosting = $server ? HostingProviderFactory::forServer($server) : null;

        if (! $hosting) {
            session()->flash('error', 'El servidor de este servicio no tiene una conexión de hosting configurada.');

            return;
        }

        if (! $username) {
            session()->flash('error', 'Este servicio no tiene una cuenta de hosting vinculada.');

            return;
        }

        $result = $suspend
            ? $hosting->suspend($username)
            : $hosting->unsuspend($username);

        $log->record($suspend ? 'whm.suspendacct' : 'whm.unsuspendacct', [
            'integration_id' => $this->whmIntegrationId(),
            'success' => $result->success,
            'message' => $result->success ? null : $result->message,
            'internal_reference' => 'Service:'.$this->service->id,
            'external_reference' => 'WhmAccount:'.$username.'@'.$server->name,
        ]);

        if ($result->success) {
            session()->flash('status', $suspend ? 'Cuenta suspendida.' : 'Cuenta reactivada.');
        } else {
            session()->flash('error', $result->message);
        }
    }

    /** Usuario WHM (cuenta) vinculado a este servicio, si existe. */
    public function whmAccount(): ?string
    {
        return $this->service->whm_username;
    }

    /**
     * ¿El servidor de este servicio tiene una conexión de hosting activa?
     * (para mostrar los botones)
     */
    public function whmEnabled(): bool
    {
        return (bool) $this->service->server?->hasPanel();
    }

    /** ID de la integración WHM global, solo para etiquetar el log (opcional). */
    private function whmIntegrationId(): ?int
    {
        return Integration::where('key', 'whm')->value('id');
    }

    private function suggestUsername(): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($this->service->client?->company ?? 'user'));
        $base = substr($base ?: 'user', 0, 6);

        return $base.$this->service->id;
    }

    public function render(): View
    {
        $catalogItems = AddonCatalogItem::with('prices')->where('is_active', true)->orderBy('name')->get();
        $selectedCatalogItem = $this->addonCatalogId !== ''
            ? $catalogItems->firstWhere('id', (int) $this->addonCatalogId)
            : null;

        return view('livewire.admin.services.service-show', [
            'addonStatusOptions' => AddonStatus::options(),
            'catalogItems' => $catalogItems,
            'selectedCatalogItem' => $selectedCatalogItem,
            'promotions' => Promotion::where('is_active', true)->orderBy('name')->get(),
            'whmEnabled' => $this->whmEnabled(),
            'whmAccount' => $this->whmAccount(),
            'canShowMailboxes' => $this->canShowMailboxes(),
            'canLinkAccount' => $this->canLinkAccount(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
