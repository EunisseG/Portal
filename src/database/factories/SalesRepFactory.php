<?php

namespace Database\Factories;

use App\Domain\Sales\Models\SalesRep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesRep>
 */
class SalesRepFactory extends Factory
{
    protected $model = SalesRep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('55 #### ####'),
            'is_active' => true,
        ];
    }
}
