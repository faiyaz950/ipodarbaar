<?php

namespace App\Services;

use App\Models\Ipo;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Year-by-year IPO statistics for the report card and /ipo/{year} pages.
 * Results are cached; flush() is called after syncs and admin edits.
 */
class IpoStatsService
{
    private const VERSION_KEY = 'stats:version';

    private const TTL_SECONDS = 3600;

    /**
     * Years that have at least one IPO, newest first.
     *
     * @return array<int, int>
     */
    public function years(): array
    {
        return $this->remember('years', fn (): array => Ipo::query()
            ->whereNotNull('open_date')
            ->pluck('open_date')
            ->map(fn ($date): int => $date->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all());
    }

    /**
     * @return array{year: int, count: int, mainboard: int, sme: int, raised: float, raised_mainboard: float, raised_sme: float, with_listing: int, avg_gain: float|null, median_gain: float|null, positive_share: float|null, best: array<int, array<string, mixed>>, worst: array<int, array<string, mixed>>, largest: array<int, array<string, mixed>>, months: array<int, array{count: int, raised: float}>}
     */
    public function forYear(int $year): array
    {
        return $this->remember('year:'.$year, function () use ($year): array {
            $ipos = Ipo::query()->whereYear('open_date', $year)->orderBy('open_date')->get();
            $listed = $ipos->filter(fn (Ipo $ipo): bool => $ipo->listingGainPercent() !== null);
            $gains = $listed->map(fn (Ipo $ipo): float => $ipo->listingGainPercent())->sort()->values();

            $months = [];
            foreach (range(1, 12) as $month) {
                $inMonth = $ipos->filter(fn (Ipo $ipo): bool => $ipo->open_date->month === $month);
                $months[$month] = ['count' => $inMonth->count(), 'raised' => round((float) $inMonth->sum('issue_size'), 2)];
            }

            return [
                'year' => $year,
                'count' => $ipos->count(),
                'mainboard' => $ipos->where('type', 'mainboard')->count(),
                'sme' => $ipos->where('type', 'sme')->count(),
                'raised' => round((float) $ipos->sum('issue_size'), 2),
                'raised_mainboard' => round((float) $ipos->where('type', 'mainboard')->sum('issue_size'), 2),
                'raised_sme' => round((float) $ipos->where('type', 'sme')->sum('issue_size'), 2),
                'with_listing' => $gains->count(),
                'avg_gain' => $gains->isEmpty() ? null : round($gains->avg(), 2),
                'median_gain' => $this->median($gains),
                'positive_share' => $gains->isEmpty() ? null : round($gains->filter(fn (float $gain): bool => $gain > 0)->count() * 100 / $gains->count(), 1),
                'best' => $listed->sortByDesc(fn (Ipo $ipo): float => $ipo->listingGainPercent())->take(10)->map($this->row(...))->values()->all(),
                'worst' => $listed->sortBy(fn (Ipo $ipo): float => $ipo->listingGainPercent())->take(10)->map($this->row(...))->values()->all(),
                'largest' => $ipos->whereNotNull('issue_size')->sortByDesc('issue_size')->take(10)->map($this->row(...))->values()->all(),
                'months' => $months,
            ];
        });
    }

    /**
     * How close the last GMP before listing came to the actual listing price, for every IPO
     * with both on record. Error is in percentage points of the issue price (+ = listed higher).
     *
     * @return array<string, mixed>
     */
    public function gmpAccuracy(): array
    {
        return $this->remember('gmp-accuracy', function (): array {
            $ipos = Ipo::query()
                ->whereNotNull('listing_price')->whereNotNull('listing_gmp')->where('price', '>', 0)
                ->whereDate('listing_date', '<=', now()->toDateString())
                ->orderByDesc('listing_date')->get()
                // A listing below 0.3x or above 3x the issue price means the price on file is wrong, not the GMP.
                ->filter(fn (Ipo $ipo): bool => $ipo->gmpErrorPoints() !== null && $ipo->listing_price >= $ipo->price * 0.3 && $ipo->listing_price <= $ipo->price * 3)
                ->values();

            $bands = [
                ['GMP zero or negative', null, 0.0],
                ['GMP up to 10%', 0.0, 10.0],
                ['GMP 10% to 25%', 10.0, 25.0],
                ['GMP 25% to 50%', 25.0, 50.0],
                ['GMP above 50%', 50.0, null],
            ];

            return [
                'summary' => $this->accuracySummary($ipos),
                'since' => $ipos->last()?->listing_date?->format('M Y'),
                'boards' => [
                    'Mainboard' => $this->accuracySummary($ipos->where('type', 'mainboard')),
                    'SME' => $this->accuracySummary($ipos->where('type', 'sme')),
                ],
                'bands' => collect($bands)->map(function (array $band) use ($ipos): array {
                    [$label, $from, $to] = $band;
                    $in = $ipos->filter(fn (Ipo $ipo): bool => ($from === null || $ipo->gmpEstimatePercent() > $from) && ($to === null || $ipo->gmpEstimatePercent() <= $to));

                    return [
                        'label' => $label,
                        'count' => $in->count(),
                        'expected' => $in->isEmpty() ? null : round($in->avg(fn (Ipo $ipo): float => $ipo->gmpEstimatePercent()), 2),
                        'actual' => $in->isEmpty() ? null : round($in->avg(fn (Ipo $ipo): float => $ipo->listingGainPercent()), 2),
                        'premium_share' => $in->isEmpty() ? null : round($in->filter(fn (Ipo $ipo): bool => $ipo->listingGainPercent() > 0)->count() * 100 / $in->count(), 1),
                    ];
                })->all(),
                'months' => $ipos->groupBy(fn (Ipo $ipo): string => $ipo->listing_date->format('Y-m'))
                    ->map(fn (Collection $in, string $month): array => ['label' => Carbon::parse($month.'-01')->format('M Y')] + $this->accuracySummary($in))
                    ->take(12)->values()->all(),
                'misses' => $ipos->sortByDesc(fn (Ipo $ipo): float => abs($ipo->gmpErrorPoints()))->take(10)->map($this->accuracyRow(...))->values()->all(),
                'recent' => $ipos->take(40)->map($this->accuracyRow(...))->values()->all(),
            ];
        });
    }

    /**
     * @param  Collection<int, Ipo>  $ipos
     * @return array{count: int, median_abs_error: float|null, mean_error: float|null, within5: float|null, within10: float|null, direction: float|null, over: float|null}
     */
    private function accuracySummary(Collection $ipos): array
    {
        $count = $ipos->count();
        $share = fn (callable $test): ?float => $count ? round($ipos->filter($test)->count() * 100 / $count, 1) : null;

        return [
            'count' => $count,
            'median_abs_error' => $this->median($ipos->map(fn (Ipo $ipo): float => abs($ipo->gmpErrorPoints()))->sort()->values()),
            'mean_error' => $count ? round($ipos->avg(fn (Ipo $ipo): float => $ipo->gmpErrorPoints()), 2) : null,
            'within5' => $share(fn (Ipo $ipo): bool => abs($ipo->gmpErrorPoints()) <= 5),
            'within10' => $share(fn (Ipo $ipo): bool => abs($ipo->gmpErrorPoints()) <= 10),
            // Right direction: a positive GMP and a listing above the issue price, or neither.
            'direction' => $share(fn (Ipo $ipo): bool => ($ipo->listing_gmp > 0) === ($ipo->listingGainPercent() > 0)),
            'over' => $share(fn (Ipo $ipo): bool => $ipo->gmpErrorPoints() < 0),
        ];
    }

    /**
     * @return array{name: string, url: string, type: string, date: string, price: float, gmp: float, expected_price: float, expected: float, listing_price: float, actual: float, error: float}
     */
    private function accuracyRow(Ipo $ipo): array
    {
        return [
            'name' => $ipo->name,
            'url' => $ipo->url(),
            'type' => $ipo->typeLabel(),
            'date' => $ipo->listing_date->format('j M Y'),
            'price' => $ipo->price,
            'gmp' => $ipo->listing_gmp,
            'expected_price' => round($ipo->price + $ipo->listing_gmp, 2),
            'expected' => $ipo->gmpEstimatePercent(),
            'listing_price' => $ipo->listing_price,
            'actual' => $ipo->listingGainPercent(),
            'error' => $ipo->gmpErrorPoints(),
        ];
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (string) microtime(true));
    }

    /**
     * @return array{name: string, url: string, type: string, price: float|null, listing_price: float|null, gain: float|null, issue_size: float|null, date: string|null}
     */
    private function row(Ipo $ipo): array
    {
        return [
            'name' => $ipo->name,
            'url' => $ipo->url(),
            'type' => $ipo->typeLabel(),
            'price' => $ipo->price,
            'listing_price' => $ipo->listing_price,
            'gain' => $ipo->listingGainPercent(),
            'issue_size' => $ipo->issue_size,
            'date' => ($ipo->listing_date ?? $ipo->open_date)?->format('j M'),
        ];
    }

    /**
     * @param  Collection<int, float>  $sorted
     */
    private function median(Collection $sorted): ?float
    {
        $count = $sorted->count();
        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        return round($count % 2 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2, 2);
    }

    private function remember(string $key, Closure $callback): mixed
    {
        $version = Cache::rememberForever(self::VERSION_KEY, fn (): string => (string) microtime(true));

        return Cache::remember("stats:{$version}:{$key}", self::TTL_SECONDS, $callback);
    }
}
