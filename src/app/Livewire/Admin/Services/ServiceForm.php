<?php

namespace App\Livewire\Admin\Services;

use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Plan;
use App\Domain\Services\Models\Server;
use App\Domain\Services\Models\Service;
use App\Domain\Billing\Services\PricingService;
use App\Domain\Services\Services\ServiceService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;

class ServiceForm extends Component
{
    public ?Service $service = null;

    public string $client_id = '';
    /** Texto para buscar/filtrar clientes en el selector. */
    public string $clientSearch = '';
    /** Ejecutivo de ventas responsable de este servicio (id del catálogo). */
    public ?int $sales_rep_id = null;
    public string $server_id = '';
    public string $plan_id = '';
    public string $type = ServiceType::Hosting->value;
    public string $name = '';
    public string $status = ServiceStatus::Active->value;
    public string $billing_cycle = BillingCycle::Annual->value;
    public string $unit_price = '';   // en pesos (decimal)
    public string $tax_rate = '16';
    public string $starts_at = '';
    public string $expires_at = '';
    public string $notes = '';

    public bool $expiresManuallySet = false;

    // Dominio ligado al pedido (solo aplica a hosting).
    /** Modo: 'existing' = ligar uno del cliente, 'new' = agregar uno. */
    public string $domainMode = 'existing';
    public string $domainId = '';
    public string $domainName = '';
    /** Origen del dominio nuevo: 'owned' = ya lo tiene, 'purchase' = comprar con nosotros. */
    public string $domainOrigin = 'owned';
    public bool $domainIsPremium = false;
    /** Precio del dominio (decimal) cuando aplica costo. */
    public string $domainPrice = '';

    public function mount(?Service $service = null): void
    {
        if ($service && $service->exists) {
            Gate::authorize('update', Service::class);
            $this->service = $service;
            $this->client_id = (string) $service->client_id;
            $this->sales_rep_id = $service->sales_rep_id;
            $this->server_id = (string) ($service->server_id ?? '');
            $this->plan_id = (string) ($service->plan_id ?? '');
            $this->type = $service->type->value;
            $this->name = $service->name;
            $this->status = $service->status->value;
            $this->billing_cycle = $service->billing_cycle?->value ?? BillingCycle::Annual->value;
            $this->unit_price = number_format(($service->unit_price_cents ?: $service->price_cents) / 100, 2, '.', '');
            $this->tax_rate = (string) (float) ($service->tax_rate ?: 0);
            $this->starts_at = $service->starts_at?->toDateString() ?? '';
            $this->expires_at = $service->expires_at?->toDateString() ?? '';
            $this->expiresManuallySet = $service->expires_at !== null;
            $this->notes = (string) $service->notes;
        } else {
            Gate::authorize('create', Service::class);

            $clientId = request()->query('client');
            if ($clientId && Client::whereKey($clientId)->exists()) {
                $this->client_id = (string) $clientId;
            }
        }
    }

    public function isEditing(): bool
    {
        return $this->service !== null;
    }

    // Al cambiar el tipo, se limpia el plan seleccionado (los planes se filtran
    // por tipo) y, si el nuevo tipo no es hosting, se limpia el servidor (el
    // apartado de servidor solo aplica a hosting).
    public function updatedType(): void
    {
        $this->plan_id = '';

        if ($this->type !== ServiceType::Hosting->value) {
            $this->server_id = '';
            // El bloque de dominio solo aplica a hosting: se restablece.
            $this->domainMode = 'existing';
            $this->domainId = '';
            $this->domainName = '';
            $this->domainOrigin = 'owned';
            $this->domainIsPremium = false;
            $this->domainPrice = '';
        }
    }

    /**
     * Extensiones que registramos gratis (salvo que sean premium).
     *
     * @var list<string>
     */
    private const FREE_TLDS = ['.com', '.com.mx'];

    /**
     * ¿El dominio nuevo se cobra? Solo cuando el modo es 'new', el origen es
     * 'purchase', y (es premium O la extensión no está en la lista gratuita).
     */
    public function domainIsChargeable(): bool
    {
        if ($this->domainMode !== 'new' || $this->domainOrigin !== 'purchase') {
            return false;
        }

        if ($this->domainIsPremium) {
            return true;
        }

        return ! $this->hasFreeTld($this->domainName);
    }

    /** ¿El dominio nuevo (comprado) es gratuito? (.com/.com.mx no premium) */
    public function domainIsFree(): bool
    {
        return $this->domainMode === 'new'
            && $this->domainOrigin === 'purchase'
            && ! $this->domainIsChargeable();
    }

    /** Costo del dominio (decimal, en pesos) que se suma al total. */
    public function domainCost(): float
    {
        return $this->domainIsChargeable() ? (float) ($this->domainPrice ?: 0) : 0.0;
    }

    private function hasFreeTld(string $name): bool
    {
        $name = strtolower(trim($name));

        foreach (self::FREE_TLDS as $tld) {
            if (str_ends_with($name, $tld)) {
                return true;
            }
        }

        return false;
    }

    /** ¿El tipo actual usa servidor? (solo hosting) */
    public function usesServer(): bool
    {
        return $this->type === ServiceType::Hosting->value;
    }

    /**
     * ¿Se muestra el campo "Nombre del servicio"? Solo cuando NO hay plan
     * (con plan, el nombre se toma automáticamente del plan).
     */
    public function showsNameField(): bool
    {
        return $this->plan_id === '';
    }

    /** Etiqueta del cliente seleccionado (para el campo autocomplete). */
    public function selectedClientLabel(): string
    {
        if ($this->client_id === '') {
            return '';
        }

        $client = Client::with('user')->find($this->client_id);
        if (! $client) {
            return '';
        }

        return $client->user->name.' — '.($client->company ?: $client->user->email);
    }

    /** Selecciona un cliente desde el autocomplete. */
    public function selectClient(int $id): void
    {
        if (Client::whereKey($id)->exists()) {
            $this->client_id = (string) $id;
            $this->clientSearch = '';
        }
    }

    /** Limpia la selección de cliente. */
    public function clearClient(): void
    {
        $this->client_id = '';
        $this->clientSearch = '';
    }

    // Al elegir un plan, si el ciclo actual tiene precio en el plan, autocompleta.
    public function updatedPlanId(): void
    {
        $this->applyPlanPricing();
    }

    public function updatedBillingCycle(): void
    {
        $this->applyPlanPricing();
        $this->recalculateExpiry();
    }

    public function updatedStartsAt(): void
    {
        $this->recalculateExpiry();
    }

    public function updatedExpiresAt(): void
    {
        $this->expiresManuallySet = $this->expires_at !== '';
    }

    /**
     * Copia precio unitario e IVA del plan+ciclo seleccionados a los campos
     * (siguen siendo editables). Si no hay plan/precio, no toca los valores.
     */
    private function applyPlanPricing(): void
    {
        if ($this->plan_id === '') {
            return;
        }

        $plan = Plan::with('prices')->find($this->plan_id);
        if (! $plan) {
            return;
        }

        // Autocompleta el nombre si está vacío.
        if ($this->name === '') {
            $this->name = $plan->name;
        }

        $price = $plan->prices->firstWhere('billing_cycle', BillingCycle::tryFrom($this->billing_cycle));
        if ($price) {
            $this->unit_price = number_format($price->unit_price_cents / 100, 2, '.', '');
            $this->tax_rate = (string) (float) $price->tax_rate;
        }
    }

    private function recalculateExpiry(): void
    {
        if ($this->expiresManuallySet || $this->starts_at === '') {
            return;
        }

        $cycle = BillingCycle::tryFrom($this->billing_cycle);
        if (! $cycle) {
            return;
        }

        try {
            $this->expires_at = Carbon::parse($this->starts_at)->addMonths($cycle->months())->toDateString();
        } catch (\Exception) {
            // fecha inválida: no hacemos nada.
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'client_id' => ['required', Rule::exists('clients', 'id')],
            'sales_rep_id' => ['nullable', 'integer', Rule::exists('sales_reps', 'id')],
            'server_id' => ['nullable', Rule::exists('servers', 'id')],
            'plan_id' => ['nullable', Rule::exists('plans', 'id')],
            'type' => ['required', new Enum(ServiceType::class)],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', new Enum(ServiceStatus::class)],
            'billing_cycle' => ['required', new Enum(BillingCycle::class)],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Dominio (solo hosting).
            'domainMode' => ['nullable', 'in:existing,new'],
            'domainId' => ['nullable', Rule::exists('domains', 'id')],
            'domainName' => ['nullable', 'string', 'max:255'],
            'domainOrigin' => ['nullable', 'in:owned,purchase'],
            'domainIsPremium' => ['boolean'],
            // El precio es obligatorio solo cuando el dominio nuevo se cobra.
            'domainPrice' => [$this->domainIsChargeable() ? 'required' : 'nullable', 'numeric', 'min:0'],
        ];
    }

    /** Valida el dominio según el modo cuando el pedido es de hosting. */
    private function validateDomain(): void
    {
        if ($this->type !== ServiceType::Hosting->value) {
            return;
        }

        if ($this->domainMode === 'existing') {
            $this->validate(['domainId' => ['required', Rule::exists('domains', 'id')]], [
                'domainId.required' => 'Selecciona un dominio del cliente o cambia a “Agregar nuevo”.',
            ]);
        } elseif ($this->domainMode === 'new') {
            $this->validate(['domainName' => ['required', 'string', 'max:255']], [
                'domainName.required' => 'Escribe el nombre del dominio.',
            ]);
        }
    }

    /** ¿El pedido lleva bloque de dominio? (solo hosting) */
    public function usesDomain(): bool
    {
        return $this->type === ServiceType::Hosting->value;
    }

    /** IVA aplicado (%) como flotante. */
    private function taxRateValue(): float
    {
        return (float) ($this->tax_rate ?: 0);
    }

    /** Aplica el IVA vigente a un monto en pesos. */
    private function withTax(float $amount): float
    {
        return $amount + ($amount * $this->taxRateValue() / 100);
    }

    /**
     * Desglose del pedido por concepto para la previsualización. Cada concepto
     * (servicio y, si aplica, dominio) muestra su base, su IVA y su total.
     *
     * @return array<int, array{label:string, base:float, tax:float, total:float}>
     */
    public function breakdownPreview(): array
    {
        $rows = [];

        $unit = (float) ($this->unit_price ?: 0);
        $rows[] = [
            'label' => $this->name !== '' ? $this->name : 'Servicio',
            'base' => $unit,
            'tax' => $unit * $this->taxRateValue() / 100,
            'total' => $this->withTax($unit),
        ];

        $domain = $this->domainCost();
        if ($domain > 0) {
            $rows[] = [
                'label' => 'Dominio'.($this->domainName !== '' ? ' ('.$this->domainName.')' : ''),
                'base' => $domain,
                'tax' => $domain * $this->taxRateValue() / 100,
                'total' => $this->withTax($domain),
            ];
        }

        return $rows;
    }

    /** Total general (suma de cada concepto con su IVA). */
    public function totalPreview(): string
    {
        $total = array_sum(array_column($this->breakdownPreview(), 'total'));

        return number_format($total, 2).' MXN';
    }

    public function save(ServiceService $services, PricingService $pricing): mixed
    {
        $data = $this->validate();
        $this->validateDomain();

        // El precio unitario del pedido es solo el del servicio. El dominio se
        // cobra como un concepto aparte (complemento), para que cada concepto
        // lleve su propio IVA y el total quede desglosado.
        $unitCents = Money::fromDecimal($data['unit_price'])->amountInCents;
        $taxRate = (float) $data['tax_rate'];

        $payload = [
            'client_id' => $data['client_id'],
            'sales_rep_id' => $data['sales_rep_id'] ?: null,
            'server_id' => $data['server_id'] ?: null,
            'plan_id' => $data['plan_id'] ?: null,
            'type' => $data['type'],
            'name' => $data['name'],
            'status' => $data['status'],
            'billing_cycle' => $data['billing_cycle'],
            'unit_price_cents' => $unitCents,
            'tax_rate' => $taxRate,
            'currency' => Money::DEFAULT_CURRENCY,
            'starts_at' => $data['starts_at'] ?: null,
            'expires_at' => $data['expires_at'] ?: null,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->isEditing()) {
            $services->update($this->service, $payload);
            $this->linkDomain($this->service, $data);
            $this->syncDomainCharge($this->service, $data);
            // Recalcula el desglose respetando complementos y promoción actuales.
            $pricing->recalculate($this->service->refresh()->load('addons'));
            session()->flash('status', 'Servicio actualizado.');
        } else {
            $service = $services->create($payload);
            $this->linkDomain($service, $data);
            $this->syncDomainCharge($service, $data);
            $pricing->recalculate($service->refresh()->load('addons'));
            session()->flash('status', 'Servicio creado.');
        }

        return $this->redirect(route('admin.services.index'), navigate: true);
    }

    /**
     * Liga un dominio al servicio (solo hosting): uno existente del cliente o
     * uno nuevo creado en el momento.
     *
     * @param  array<string, mixed>  $data
     */
    private function linkDomain(Service $service, array $data): void
    {
        if ($this->type !== ServiceType::Hosting->value) {
            return;
        }

        $mode = $data['domainMode'] ?? '';

        if ($mode === 'existing' && ! empty($data['domainId'])) {
            // Solo ligar dominios del mismo cliente.
            Domain::where('id', $data['domainId'])
                ->where('client_id', $service->client_id)
                ->update(['service_id' => $service->id]);
        } elseif ($mode === 'new' && ! empty($data['domainName'])) {
            // Nota de origen/costo para trazabilidad.
            if ($this->domainOrigin === 'owned') {
                $note = 'El cliente ya tiene el dominio en otro registrador (sin costo).';
            } elseif ($this->domainIsChargeable()) {
                $note = 'Comprado con nosotros'.($this->domainIsPremium ? ' (premium)' : '').'. Costo: '.number_format($this->domainCost(), 2).' MXN.';
            } else {
                $note = 'Comprado con nosotros (gratis).';
            }

            Domain::create([
                'client_id' => $service->client_id,
                'service_id' => $service->id,
                'name' => $data['domainName'],
                'status' => DomainStatus::Pending->value,
                'auto_renew' => false,
                'notes' => $note,
            ]);
        }
    }

    /**
     * Refleja el costo del dominio como un complemento del pedido, para que
     * aparezca como un concepto propio (con su IVA) en el desglose y en la
     * factura. Si el dominio no se cobra, elimina el complemento previo.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncDomainCharge(Service $service, array $data): void
    {
        // Complemento "dominio" existente de este pedido (marcado por bandera).
        $existing = Addon::where('service_id', $service->id)
            ->where('is_domain', true)
            ->first();

        $chargeable = $this->type === ServiceType::Hosting->value
            && ($data['domainMode'] ?? '') === 'new'
            && $this->domainIsChargeable();

        if (! $chargeable) {
            $existing?->delete();

            return;
        }

        $label = 'Dominio'.(! empty($data['domainName']) ? ' ('.$data['domainName'].')' : '');
        $priceCents = Money::fromDecimal((string) $this->domainCost())->amountInCents;

        $payload = [
            'service_id' => $service->id,
            'is_domain' => true,
            'name' => $label,
            'status' => AddonStatus::Active->value,
            'price_cents' => $priceCents,
            'currency' => Money::DEFAULT_CURRENCY,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            Addon::create($payload);
        }
    }

    public function render(): View
    {
        // Planes activos del tipo seleccionado.
        $plans = Plan::with('prices')
            ->where('is_active', true)
            ->where('type', $this->type)
            ->orderBy('name')
            ->get();

        // Ciclos disponibles del plan elegido (si hay), para el selector de ciclo.
        $planCycles = [];
        if ($this->plan_id !== '') {
            $plan = $plans->firstWhere('id', (int) $this->plan_id);
            if ($plan) {
                $planCycles = $plan->prices
                    ->mapWithKeys(fn ($p) => [$p->billing_cycle->value => $p->billing_cycle->label()])
                    ->all();
            }
        }

        // Resultados del autocomplete de cliente: al abrir sin texto muestra
        // toda la lista (hasta 50); al escribir, filtra por nombre/empresa/correo.
        $clientResults = Client::with('user')
            ->when(trim($this->clientSearch) !== '', function ($q) {
                $term = '%'.trim($this->clientSearch).'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('company', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->limit(50)
            ->get()
            ->sortBy(fn ($c) => $c->user->name)
            ->values();

        // Dominios del cliente seleccionado, para "ligar existente".
        $clientDomains = collect();
        if ($this->client_id !== '') {
            $clientDomains = Domain::where('client_id', $this->client_id)
                ->orderBy('name')
                ->get(['id', 'name', 'service_id']);
        }

        return view('livewire.admin.services.service-form', [
            'clientResults' => $clientResults,
            'selectedClientLabel' => $this->selectedClientLabel(),
            'salesReps' => \App\Domain\Sales\Models\SalesRep::where('is_active', true)->orderBy('name')->get(),
            'servers' => Server::orderBy('name')->get(),
            'plans' => $plans,
            'planCycles' => $planCycles,
            'hasPlans' => $plans->isNotEmpty(),
            'usesServer' => $this->usesServer(),
            'usesDomain' => $this->usesDomain(),
            'clientDomains' => $clientDomains,
            'showsNameField' => $this->showsNameField(),
            'typeOptions' => ServiceType::options(),
            'statusOptions' => ServiceStatus::options(),
            'cycleOptions' => BillingCycle::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
