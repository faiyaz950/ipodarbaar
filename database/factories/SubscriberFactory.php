<?php

namespace Database\Factories;

use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'frequency' => 'daily',
            'consent_at' => now()->subDay(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => ['confirmed_at' => now()->subDay()]);
    }

    public function unsubscribed(): static
    {
        return $this->confirmed()->state(fn (): array => ['unsubscribed_at' => now()->subHour()]);
    }

    public function weekly(): static
    {
        return $this->state(fn (): array => ['frequency' => 'weekly']);
    }
}
