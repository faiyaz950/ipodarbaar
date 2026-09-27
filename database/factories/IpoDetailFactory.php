<?php

namespace Database\Factories;

use App\Models\Ipo;
use App\Models\IpoDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpoDetail>
 */
class IpoDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ipo_id' => Ipo::factory(),
            'fresh_issue_cr' => 150,
            'ofs_cr' => 100,
            'face_value' => 10,
            'market_cap_cr' => 1200,
            'retail_quota' => 35,
            'nii_quota' => 15,
            'qib_quota' => 50,
            'promoter_holding_pre' => 92.5,
            'promoter_holding_post' => 68.4,
            'promoters' => 'Rakesh Sharma and Anita Sharma',
            'lead_managers' => "Axis Capital\nICICI Securities",
            'objects' => "Repayment of borrowings\nSetting up a new plant\nGeneral corporate purposes",
            'pe_pre' => 24.5,
            'pe_post' => 31.2,
            'roe' => 18.4,
            'debt_equity' => 0.45,
            'rhp_url' => 'https://www.sebi.gov.in/filings/public-issues/example-rhp.pdf',
        ];
    }
}
