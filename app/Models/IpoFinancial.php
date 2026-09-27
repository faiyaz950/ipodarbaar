<?php

namespace App\Models;

use Database\Factories\IpoFinancialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One period of a company's restated financials (₹ crore), from the offer document.
 */
class IpoFinancial extends Model
{
    /** @use HasFactory<IpoFinancialFactory> */
    use HasFactory;

    public const METRICS = [
        'revenue' => 'Revenue',
        'pat' => 'Profit after tax',
        'net_worth' => 'Net worth',
        'total_assets' => 'Total assets',
        'borrowings' => 'Total borrowings',
    ];

    protected $fillable = ['period', 'period_end', 'revenue', 'pat', 'net_worth', 'total_assets', 'borrowings'];

    protected function casts(): array
    {
        return [
            'period_end' => 'date',
            'revenue' => 'float',
            'pat' => 'float',
            'net_worth' => 'float',
            'total_assets' => 'float',
            'borrowings' => 'float',
        ];
    }

    public function ipo(): BelongsTo
    {
        return $this->belongsTo(Ipo::class);
    }

    public function patMargin(): ?float
    {
        return ($this->revenue && $this->pat !== null) ? round($this->pat / $this->revenue * 100, 2) : null;
    }
}
