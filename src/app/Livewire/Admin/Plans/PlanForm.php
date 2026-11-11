<?php

namespace App\Livewire\Admin\Plans;

use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Plan;
use App\Domain\Services\Services\PlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;

class PlanForm extends Component
{
    public ?Plan $plan = null;

    public string $name = '';
    public string $type = '';
    /** Categoría de productos donde se ofrece el plan (p. ej. "Hosting"). */
    public string $category_id = '';
    public string $description = '';
    public bool $is_active = true;

    /**
     * Ciclos habilitados: [billing_cycle => bool].
     *
     * @var array<string, bool>
     */
    public array $enabled = [];

    /**
     * Precio unitario por ciclo (decimal, en pesos): [billing_cycle => string].
     *
     * @var array<string, string>
     */
    public array $unitPrice = [];

    /**
     * IVA (%) por ciclo: [billing_cycle => string].
     *
     * @var array<string, string>
     */
    public array $taxRate = [];

    public function mount(?Plan $plan = null): void
    {
        $this->type = ServiceType::Hosting->value;

        // Inicializa los ciclos (deshabilitados, IVA 16% por defecto).
        foreach (BillingCycle::cases() as $cycle) {
            $this->enabled[$cycle->value] = false;
            $this->unitPrice[$cycle->value] = '';
            $this->taxRate[$cycle->value] = '16';
        }

        if ($plan && $plan->exists) {
            Gate::authorize('update', Plan::class);
            $this->plan = $plan->load('prices');
            $this->name = $plan->name;
            $this->type = $plan->type->value;
            $this->category_id = (string) ($plan->category_id ?? '');
            $this->description = (string) $plan->description;
            $this->is_active = $plan->is_active;

            foreach ($plan->prices as $price) {
                $c = $price->billing_cycle->value;
                $this->enabled[$c] = true;
                $this->unitPrice[$c] = number_format($price->unit_price_cents / 100, 2, '.', '');
                $this->taxRate[$c] = (string) (float) $price->tax_rate;
            }
        } else {
            Gate::authorize('create', Plan::class);
        }
    }

    public function isEditing(): bool
    {
        return $this->plan !== null;
    }

    public function save(PlanService $plans): mixed
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ServiceType::class)],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Construye el mapa de precios de los ciclos habilitados y valida.
        $prices = [];
        foreach (BillingCycle::cases() as $cycle) {
            $c = $cycle->value;
            if (! ($this->enabled[$c] ?? false)) {
                continue;
            }

            $unit = $this->unitPrice[$c] ?? '';
            $tax = $this->taxRate[$c] ?? '16';

            if ($unit === '' || ! is_numeric($unit) || (float) $unit < 0) {
                $this->addError("unitPrice.$c", 'Ingresa un precio válido para '.$cycle->label().'.');

                return null;
            }
            if (! is_numeric($tax) || (float) $tax < 0) {
                $this->addError("taxRate.$c", 'IVA inválido para '.$cycle->label().'.');

                return null;
            }

            $prices[$c] = ['unit_price' => $unit, 'tax_rate' => $tax];
        }

        if ($prices === []) {
            $this->addError('enabled', 'Habilita al menos un ciclo con su precio.');

            return null;
        }

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'category_id' => $this->category_id !== '' ? (int) $this->category_id : null,
            'description' => $this->description ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->isEditing()) {
            $plans->update($this->plan, $data, $prices);
            session()->flash('status', 'Plan actualizado.');
        } else {
            $plans->create($data, $prices);
            session()->flash('status', 'Plan creado.');
        }

        return $this->redirect(route('admin.plans.index'), navigate: true);
    }

    /**
     * Total calculado (unitario + IVA) para mostrar en vivo. Devuelve string formateado.
     */
    public function totalFor(string $cycle): string
    {
        $unit = (float) ($this->unitPrice[$cycle] ?? 0);
        $tax = (float) ($this->taxRate[$cycle] ?? 0);
        $total = $unit + ($unit * $tax / 100);

        return number_format($total, 2).' MXN';
    }

    public function render(): View
    {
        return view('livewire.admin.plans.plan-form', [
            'typeOptions' => ServiceType::options(),
            'categoryOptions' => \App\Domain\Services\Models\ProductCategory::orderBy('title')->pluck('title', 'id'),
            'cycles' => BillingCycle::cases(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
