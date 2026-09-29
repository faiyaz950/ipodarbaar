<?php

namespace Database\Factories;

use App\Models\BlogAuthor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogAuthor>
 */
class BlogAuthorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->name();

        return ['name' => $name, 'slug' => Str::slug($name), 'role' => 'Analyst', 'bio' => fake()->sentence(12)];
    }
}
