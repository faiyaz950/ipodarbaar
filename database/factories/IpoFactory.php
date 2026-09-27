<?php

namespace Database\Factories;

use App\Models\Ipo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Dates are relative to "now", so pair the states with a frozen clock in tests.
 *
 * @extends Factory<Ipo>
 */
class IpoFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true)).' '.fake()->randomElement(['Industries', 'Technologies', 'Foods', 'Infra']);

        return [
            'api_id' => fake()->unique()->numberBetween(10_000, 99_999),
            'slug' => Str::slug($name).'-ipo',
            'name' => $name,
            'title' => $name.' IPO',
            'type' => 'mainboard',
            'price_min' => 110,
            'price' => 120,
            'gmp' => 15,
            'issue_size' => 250,
            'lot_size' => 125,
            'open_date' => now()->addDays(3)->toDateString(),
            'close_date' => now()->addDays(5)->toDateString(),
            'listing_date' => now()->addDays(10)->toDateString(),
            'source_created_at' => now()->subDays(2),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'open_date' => now()->subDay()->toDateString(),
            'close_date' => now()->addDay()->toDateString(),
            'listing_date' => now()->addDays(6)->toDateString(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (): array => [
            'open_date' => now()->addDays(3)->toDateString(),
            'close_date' => now()->addDays(5)->toDateString(),
            'listing_date' => now()->addDays(10)->toDateString(),
        ]);
    }

    public function listed(): static
    {
        return $this->state(fn (): array => [
            'open_date' => now()->subDays(12)->toDateString(),
            'close_date' => now()->subDays(10)->toDateString(),
            'listing_date' => now()->subDays(5)->toDateString(),
        ]);
    }

    public function sme(): static
    {
        return $this->state(fn (): array => ['type' => 'sme', 'lot_size' => 1200, 'issue_size' => 40]);
    }
}
