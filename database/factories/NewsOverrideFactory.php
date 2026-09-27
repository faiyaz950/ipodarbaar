<?php

namespace Database\Factories;

use App\Models\NewsOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsOverride>
 */
class NewsOverrideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'news_id' => fake()->unique()->numberBetween(1, 100000),
            'headline' => fake()->sentence(8),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['headline' => null, 'is_hidden' => true]);
    }
}
