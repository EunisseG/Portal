<?php

namespace Database\Factories;

use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Models\Plan;
use App\Domain\Services\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    protected $model = PlanPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'billing_cycle' => BillingCycle::Monthly->value,
            'unit_price_cents' => fake()->numberBetween(10000, 200000),
            'tax_rate' => 16,
            'currency' => 'MXN',
        ];
    }
}
