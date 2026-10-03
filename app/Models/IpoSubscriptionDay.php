<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subscription (times subscribed per investor category) as it stood at the end of one bidding day.
 */
class IpoSubscriptionDay extends Model
{
    public const CATEGORIES = ['qib', 'nii', 'bnii', 'snii', 'retail', 'employee', 'total'];

    protected $fillable = ['ipo_id', 'date', 'qib', 'nii', 'bnii', 'snii', 'retail', 'employee', 'total', 'as_of', 'source'];

    protected function casts(): array
    {
        return array_fill_keys(self::CATEGORIES, 'float') + ['date' => 'date', 'as_of' => 'datetime'];
    }

    public function ipo(): BelongsTo
    {
        return $this->belongsTo(Ipo::class);
    }
}
