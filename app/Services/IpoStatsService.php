<?php

namespace App\Services;

use App\Models\Ipo;
use Closure;
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
