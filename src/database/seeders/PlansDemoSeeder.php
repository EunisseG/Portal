<?php

namespace Database\Seeders;

use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Planes de ejemplo del catálogo (desarrollo). Precios por ciclo con IVA 16%.
 */
class PlansDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->makePlan('Hosting Básico', ServiceType::Hosting, [
            BillingCycle::Monthly->value => 9900,   // $99.00
            BillingCycle::Annual->value => 99900,   // $999.00
        ]);

        $this->makePlan('Hosting Pro', ServiceType::Hosting, [
            BillingCycle::Monthly->value => 19900,
            BillingCycle::Quarterly->value => 54900,
            BillingCycle::Annual->value => 199900,
        ]);

        $this->makePlan('Correo Corporativo', ServiceType::Email, [
            BillingCycle::Monthly->value => 4900,
            BillingCycle::Annual->value => 49900,
        ]);
    }

    /**
     * @param  array<string, int>  $pricesByCycle  billing_cycle => unit_price_cents
     */
    private function makePlan(string $name, ServiceType $type, array $pricesByCycle): void
    {
        $plan = Plan::updateOrCreate(
            ['name' => $name],
            ['type' => $type->value, 'is_active' => true],
        );

        $plan->prices()->delete();
        foreach ($pricesByCycle as $cycle => $unitCents) {
            $plan->prices()->create([
                'billing_cycle' => $cycle,
                'unit_price_cents' => $unitCents,
                'tax_rate' => 16,
                'currency' => 'MXN',
            ]);
        }
    }
}
