<?php

namespace Database\Factories;

use App\Models\Ipo;
use App\Models\IpoFinancial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpoFinancial>
 */
class IpoFinancialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ipo_id' => Ipo::factory(),
            'period' => 'FY26',
            'period_end' => '2026-03-31',
            'revenue' => 820.5,
            'pat' => 64.2,
            'net_worth' => 410,
            'total_assets' => 960,
            'borrowings' => 180,
        ];
    }
}
