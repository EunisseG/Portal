<?php

namespace Database\Factories;

use App\Domain\Content\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KbCategory>
 */
class KbCategoryFactory extends Factory
{
    protected $model = KbCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
