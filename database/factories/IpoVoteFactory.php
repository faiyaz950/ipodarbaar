<?php

namespace Database\Factories;

use App\Models\Ipo;
use App\Models\IpoVote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpoVote>
 */
class IpoVoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ipo_id' => Ipo::factory(),
            'choice' => fake()->randomElement(array_keys(IpoVote::CHOICES)),
            'voter_hash' => IpoVote::hash(fake()->uuid()),
            'ip_hash' => IpoVote::hash(fake()->ipv4()),
        ];
    }
}
