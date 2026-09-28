<?php

namespace Database\Factories;

use App\Models\CorporateAction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CorporateAction>
 */
class CorporateActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->unique()->company();

        return [
            'type' => 'buyback',
            'company' => $company,
            'slug' => Str::slug($company).'-buyback-'.now()->year,
            'open_date' => now()->subDay()->toDateString(),
            'close_date' => now()->addDays(4)->toDateString(),
            'record_date' => now()->subDays(8)->toDateString(),
            'price' => 1500,
            'size_cr' => 900,
            'method' => 'Tender offer',
            'is_published' => true,
        ];
    }

    public function rights(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'rights',
            'slug' => Str::slug($attributes['company']).'-rights-issue-'.now()->year,
            'price' => 250,
            'ratio' => '1:5',
            'method' => null,
        ]);
    }

    public function ncd(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'ncd',
            'slug' => Str::slug($attributes['company']).'-ncd-'.now()->year,
            'price' => 1000,
            'coupon' => '9.50% – 10.25%',
            'tenure' => '24 to 60 months',
            'rating' => 'CRISIL AA-/Stable',
            'method' => null,
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (): array => ['open_date' => now()->addDays(5)->toDateString(), 'close_date' => now()->addDays(9)->toDateString()]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['open_date' => now()->subDays(20)->toDateString(), 'close_date' => now()->subDays(15)->toDateString()]);
    }
}
