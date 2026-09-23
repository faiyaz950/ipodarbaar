<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpoGmpHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'gmp' => 'float',
        'price' => 'float',
    ];

    public function ipo(): BelongsTo
    {
        return $this->belongsTo(Ipo::class);
    }

    /**
     * Store today's GMP for IPOs that haven't listed yet (one row per IPO per day, latest value wins).
     *
     * @param  iterable<Ipo>  $ipos
     */
    public static function record(iterable $ipos): int
    {
        $today = now()->toDateString();
        $stamp = now();
        $rows = [];

        foreach ($ipos as $ipo) {
            if ($ipo->gmp === null || $ipo->status() === 'listed') {
                continue;
            }
            $rows[] = [
                'ipo_id' => $ipo->id,
                'date' => $today,
                'gmp' => $ipo->gmp,
                'price' => $ipo->price,
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ];
        }

        if ($rows) {
            static::query()->upsert($rows, ['ipo_id', 'date'], ['gmp', 'price', 'updated_at']);
        }

        return count($rows);
    }
}
