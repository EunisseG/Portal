<?php

namespace App\Livewire\Admin\Services;

use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\BillingMode;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\AddonCatalogItem;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Complementos del admin en una sola pantalla con dos pestañas:
 *  - "Catálogo": CRUD de las plantillas reutilizables (con modo de cobro).
 *  - "Desplegados": listado (solo lectura) de los complementos ya asignados a
 *    pedidos (Service).
 */
class AddonCatalogIndex extends Component
{
    use WithPagination;

    /** Pestaña activa: 'catalog' o 'deployed'. */
    #[Url(as: 'tab')]
    public string $tab = 'catalog';

    // ---- Form del catálogo ----
    public ?int $editingId = null;
    public string $name = '';
    public string $description = '';
    public bool $is_active = true;

    /** Modo de cobro: 'one_time' o 'recurring'. */
    public string $billingMode = 'one_time';

    /** Precio para pago único (decimal). */
    public string $oneTimePrice = '';

    /** IVA (%) del pago único. */
    public string $oneTimeTaxRate = '16';

    /** @var array<string, bool> Ciclos habilitados (recurrente). */
    public array $enabled = [];

    /** @var array<string, string> Precio por ciclo (decimal). */
    public array $unitPrice = [];

    /** @var array<string, string> IVA (%) por ciclo. */
    public array $taxRate = [];

    // ---- Filtros de la pestaña "Desplegados" ----
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'estado')]
    public string $statusFilter = '';

    public function mount(): void
    {
        Gate::authorize('addons.manage');
        $this->resetCycleInputs();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['catalog', 'deployed'], true) ? $tab : 'catalog';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /** Inicializa los arreglos de ciclos (deshabilitados, IVA 16%). */
    private function resetCycleInputs(): void
    {
        $this->enabled = [];
        $this->unitPrice = [];
        $this->taxRate = [];
        foreach (BillingCycle::cases() as $cycle) {
            $this->enabled[$cycle->value] = false;
            $this->unitPrice[$cycle->value] = '';
            $this->taxRate[$cycle->value] = '16';
        }
    }

    public function edit(int $id): void
    {
        Gate::authorize('addons.manage');

        $item = AddonCatalogItem::with('prices')->findOrFail($id);
        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->description = (string) $item->description;
        $this->is_active = $item->is_active;
        $this->billingMode = $item->billing_mode?->value ?? 'one_time';
        $this->oneTimePrice = $item->price_cents ? number_format($item->price_cents / 100, 2, '.', '') : '';
        $this->oneTimeTaxRate = (string) (float) ($item->tax_rate ?? 16);

        $this->resetCycleInputs();
        foreach ($item->prices as $price) {
            $c = $price->billing_cycle->value;
            $this->enabled[$c] = true;
            $this->unitPrice[$c] = number_format($price->unit_price_cents / 100, 2, '.', '');
            $this->taxRate[$c] = (string) (float) $price->tax_rate;
        }

        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        Gate::authorize('addons.manage');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'billingMode' => ['required', Rule::enum(BillingMode::class)],
            'is_active' => ['boolean'],
        ]);

        // Resuelve los precios según el modo de cobro.
        $oneTimeCents = 0;
        // Los recurrentes llevan su IVA por ciclo; se deja 16 por omisión.
        $oneTimeTax = 16.0;
        $cyclePrices = [];

        if ($data['billingMode'] === BillingMode::OneTime->value) {
            if ($this->oneTimePrice === '' || ! is_numeric($this->oneTimePrice) || (float) $this->oneTimePrice < 0) {
                $this->addError('oneTimePrice', 'Ingresa un precio válido para el pago único.');

                return;
            }
            if (! is_numeric($this->oneTimeTaxRate) || (float) $this->oneTimeTaxRate < 0 || (float) $this->oneTimeTaxRate > 100) {
                $this->addError('oneTimeTaxRate', 'Ingresa un IVA válido (0 a 100).');

                return;
            }
            $oneTimeCents = Money::fromDecimal($this->oneTimePrice)->amountInCents;
            $oneTimeTax = (float) $this->oneTimeTaxRate;
        } else {
            foreach (BillingCycle::cases() as $cycle) {
                $c = $cycle->value;
                if (! ($this->enabled[$c] ?? false)) {
                    continue;
                }
                $unit = $this->unitPrice[$c] ?? '';
                $tax = $this->taxRate[$c] ?? '16';
                if ($unit === '' || ! is_numeric($unit) || (float) $unit < 0) {
                    $this->addError("unitPrice.$c", 'Precio inválido para '.$cycle->label().'.');

                    return;
                }
                if (! is_numeric($tax) || (float) $tax < 0) {
                    $this->addError("taxRate.$c", 'IVA inválido para '.$cycle->label().'.');

                    return;
                }
                $cyclePrices[$c] = [
                    'unit_price_cents' => Money::fromDecimal($unit)->amountInCents,
                    'tax_rate' => (float) $tax,
                ];
            }

            if ($cyclePrices === []) {
                $this->addError('enabled', 'Habilita al menos un ciclo con su precio.');

                return;
            }
            // El precio "plano" se conserva con el ciclo mensual (o el primero)
            // para compatibilidad con usos que leen price_cents.
            $oneTimeCents = $cyclePrices['monthly']['unit_price_cents'] ?? reset($cyclePrices)['unit_price_cents'];
        }

        $payload = [
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'billing_mode' => $data['billingMode'],
            'price_cents' => $oneTimeCents,
            'tax_rate' => $oneTimeTax,
            'currency' => Money::DEFAULT_CURRENCY,
            'is_active' => $data['is_active'],
        ];

        if ($this->editingId) {
            $item = AddonCatalogItem::findOrFail($this->editingId);
            $item->update($payload);
            session()->flash('status', 'Complemento del catálogo actualizado.');
        } else {
            $item = AddonCatalogItem::create($payload);
            session()->flash('status', 'Complemento agregado al catálogo.');
        }

        // Sincroniza los precios por ciclo (reemplazo completo).
        $item->prices()->delete();
        foreach ($cyclePrices as $c => $vals) {
            $item->prices()->create([
                'billing_cycle' => $c,
                'unit_price_cents' => $vals['unit_price_cents'],
                'tax_rate' => $vals['tax_rate'],
                'currency' => Money::DEFAULT_CURRENCY,
            ]);
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Gate::authorize('addons.manage');

        AddonCatalogItem::whereKey($id)->delete(); // cascade borra addon_prices

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Complemento eliminado del catálogo.');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'description', 'oneTimePrice');
        $this->oneTimeTaxRate = '16';
        $this->billingMode = 'one_time';
        $this->is_active = true;
        $this->resetCycleInputs();
        $this->resetValidation();
    }

    public function render(): View
    {
        $items = AddonCatalogItem::query()->with('prices')->orderBy('name')->get();

        // Listado de complementos desplegados (solo lectura) para la 2ª pestaña.
        $deployed = Addon::query()
            ->with(['service.client.user'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', '%'.$this->search.'%')
                        ->orWhereHas('service', fn ($s) => $s->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.admin.services.addon-catalog-index', [
            'items' => $items,
            'deployed' => $deployed,
            'statusOptions' => AddonStatus::options(),
            'billingModeOptions' => BillingMode::options(),
            'cycles' => BillingCycle::cases(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
