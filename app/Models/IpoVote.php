<?php

namespace App\Models;

use Database\Factories\IpoVoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * One visitor's answer to "Will you apply?" for an IPO. Visitors are anonymous: only
 * keyed hashes of a random cookie and of the IP address are stored.
 */
class IpoVote extends Model
{
    /** @use HasFactory<IpoVoteFactory> */
    use HasFactory;

    public const CHOICES = [
        'apply' => 'Will apply',
        'skip' => 'Will skip',
        'unsure' => 'Not sure',
    ];

    public const COOKIE = 'darbaar_voter';

    /** Households share an IP address, so a few votes per IP are allowed. */
    public const MAX_PER_IP = 5;

    protected $fillable = ['ipo_id', 'choice', 'voter_hash', 'ip_hash'];

    public function ipo(): BelongsTo
    {
        return $this->belongsTo(Ipo::class);
    }

    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    /**
     * @return array{counts: array<string, int>, total: int}
     */
    public static function results(Ipo $ipo): array
    {
        return Cache::remember(self::cacheKey($ipo), 60, function () use ($ipo): array {
            $counts = array_fill_keys(array_keys(self::CHOICES), 0);
            $rows = static::query()
                ->whereBelongsTo($ipo)
                ->selectRaw('choice, count(*) as aggregate')
                ->groupBy('choice')
                ->pluck('aggregate', 'choice');

            foreach ($rows as $choice => $count) {
                if (array_key_exists($choice, $counts)) {
                    $counts[$choice] = (int) $count;
                }
            }

            return ['counts' => $counts, 'total' => array_sum($counts)];
        });
    }

    public static function forgetResults(Ipo $ipo): void
    {
        Cache::forget(self::cacheKey($ipo));
    }

    private static function cacheKey(Ipo $ipo): string
    {
        return 'poll:'.$ipo->getKey();
    }
}
