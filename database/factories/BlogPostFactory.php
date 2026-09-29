<?php

namespace Database\Factories;

use App\Models\BlogAuthor;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(6), '.');

        return [
            'blog_author_id' => BlogAuthor::factory(),
            'category' => 'trends',
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(18),
            'takeaways' => "First takeaway\nSecond takeaway",
            'body' => '<h2>Overview</h2><p>'.fake()->paragraph(6).'</p><h2>Details</h2><p>'.fake()->paragraph(6).'</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'reading_minutes' => 2,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft', 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['status' => 'published', 'published_at' => now()->addDays(2)]);
    }
}
