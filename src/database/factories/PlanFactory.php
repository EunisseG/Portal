<?php

namespace Database\Factories;

use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Hosting Básico', 'Hosting Pro', 'Hosting Empresarial', 'Correo Corporativo']),
            'type' => ServiceType::Hosting->value,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
