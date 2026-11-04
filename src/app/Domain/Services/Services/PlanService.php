<?php

namespace App\Domain\Services\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Services\Models\Plan;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class PlanService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PlanProductSync $productSync,
    ) {}

    /**
     * @param  array{name: string, type: string, description?: ?string, is_active?: bool}  $data
     * @param  array<string, array{unit_price: float|string, tax_rate: float|string}>  $prices
     *         Mapa billing_cycle => [unit_price (decimal), tax_rate (%)]. Solo
     *         los ciclos habilitados.
     */
    public function create(array $data, array $prices): Plan
    {
        return DB::transaction(function () use ($data, $prices): Plan {
            $plan = Plan::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncPrices($plan, $prices);
            $this->productSync->sync($plan->load('prices'));

            $this->audit->log(AuditAction::PlanCreated, $plan, null, $this->snapshot($plan));

            return $plan;
        });
    }

    /**
     * @param  array{name: string, type: string, description?: ?string, is_active?: bool}  $data
     * @param  array<string, array{unit_price: float|string, tax_rate: float|string}>  $prices
     */
    public function update(Plan $plan, array $data, array $prices): Plan
    {
        return DB::transaction(function () use ($plan, $data, $prices): Plan {
            $old = $this->snapshot($plan);

            $plan->update([
                'name' => $data['name'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncPrices($plan, $prices);
            $this->productSync->sync($plan->refresh()->load('prices'));

            $this->audit->log(AuditAction::PlanUpdated, $plan, $old, $this->snapshot($plan->refresh()->load('prices')));

            return $plan;
        });
    }

    public function delete(Plan $plan): void
    {
        $this->audit->log(AuditAction::PlanDeleted, $plan, $this->snapshot($plan));

        $this->productSync->remove($plan);

        $plan->delete();
    }

    /**
     * Reemplaza los precios del plan por los ciclos habilitados provistos.
     *
     * @param  array<string, array{unit_price: float|string, tax_rate: float|string}>  $prices
     */
    private function syncPrices(Plan $plan, array $prices): void
    {
        $plan->prices()->delete();

        foreach ($prices as $cycle => $values) {
            $plan->prices()->create([
                'billing_cycle' => $cycle,
                'unit_price_cents' => Money::fromDecimal($values['unit_price'])->amountInCents,
                'tax_rate' => (float) $values['tax_rate'],
                'currency' => Money::DEFAULT_CURRENCY,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Plan $plan): array
    {
        return [
            'name' => $plan->name,
            'type' => $plan->type?->value,
            'is_active' => $plan->is_active,
            'prices' => $plan->prices->map(fn ($p) => [
                'cycle' => $p->billing_cycle?->value,
                'unit_price_cents' => $p->unit_price_cents,
                'tax_rate' => $p->tax_rate,
            ])->all(),
        ];
    }
}
