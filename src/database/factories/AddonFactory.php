<?php

namespace Database\Factories;

use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    protected $model = Addon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'name' => fake()->randomElement(['Espacio extra', 'IP dedicada', 'Backup diario', 'Certificado SSL']),
            'status' => AddonStatus::Active->value,
            'price_cents' => fake()->numberBetween(10000, 100000),
            'currency' => 'MXN',
            'expires_at' => now()->addMonths(10)->toDateString(),
            'notes' => null,
        ];
    }
}
