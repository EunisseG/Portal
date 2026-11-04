<?php

namespace Database\Factories;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\KbArticle;
use App\Domain\Content\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KbArticle>
 */
class KbArticleFactory extends Factory
{
    protected $model = KbArticle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'kb_category_id' => KbCategory::factory(),
            'author_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'cover_path' => null,
            'excerpt' => fake()->sentence(12),
            'body' => "## ".fake()->sentence(4)."\n\n".fake()->paragraph(),
            'status' => ContentStatus::Published->value,
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ContentStatus::Draft->value, 'published_at' => null]);
    }
}
