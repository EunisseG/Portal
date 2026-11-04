<?php

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'server_id' => null,
            'type' => fake()->randomElement(ServiceType::cases())->value,
            'name' => fake()->randomElement(['Hosting Básico', 'Hosting Pro', 'Correo Corporativo', 'Licencia SSL']),
            'status' => ServiceStatus::Active->value,
            'billing_cycle' => BillingCycle::Annual->value,
            'price_cents' => fake()->numberBetween(50000, 500000),
            'unit_price_cents' => fake()->numberBetween(50000, 500000),
            'tax_rate' => 16,
            'total_cents' => fake()->numberBetween(50000, 500000),
            'currency' => 'MXN',
            'starts_at' => now()->subMonths(2)->toDateString(),
            'expires_at' => now()->addMonths(10)->toDateString(),
            'notes' => null,
        ];
    }
}
