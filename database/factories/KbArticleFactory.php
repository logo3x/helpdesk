<?php

namespace Database\Factories;

use App\Models\KbArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KbArticle>
 */
class KbArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'body' => fake()->paragraphs(3, true),
            'status' => 'draft',
            'views_count' => 0,
            'helpful_count' => 0,
            'not_helpful_count' => 0,
        ];
    }

    public function published(): self
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn () => [
            'status' => 'archived',
        ]);
    }
}
