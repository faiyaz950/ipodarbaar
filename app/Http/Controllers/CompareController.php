<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Side-by-side comparison of up to three IPOs, picked via ?ipos=slug-a,slug-b.
 */
class CompareController extends Controller
{
    public const MAX_IPOS = 3;

    public function index(Request $request): View
    {
        $slugs = collect(explode(',', $request->string('ipos')->toString()))
            ->map(fn (string $slug): string => trim($slug))
            ->filter(fn (string $slug): bool => (bool) preg_match('/^[a-z0-9-]{1,120}$/', $slug))
            ->unique()
            ->take(self::MAX_IPOS)
            ->values();

        $ipos = $slugs->isEmpty() ? collect() : Ipo::query()
            ->with(['detail', 'financials'])
            ->whereIn('slug', $slugs)
            ->get()
            ->sortBy(fn (Ipo $ipo): int => $slugs->search($ipo->slug))
            ->values();

        return view('ipos.compare', [
            'ipos' => $ipos,
            'rows' => $ipos->isEmpty() ? [] : $this->rows($ipos),
            'slugs' => $ipos->pluck('slug')->all(),
        ]);
    }

    /**
     * Each row holds display values per IPO plus the column indexes holding the best value.
     *
     * @param  Collection<int, Ipo>  $ipos
     * @return array<int, array{label: string, cells: array<int, string>, best: array<int, int>}>
     */
    private function rows(Collection $ipos): array
    {
        $latest = fn (Ipo $ipo) => $ipo->financials->last();
        $cr = fn (?float $value): string => $value === null ? '—' : '₹'.Ipo::num($value).' Cr';
        $pct = fn (?float $value): string => $value === null ? '—' : Ipo::num($value).'%';
        $times = fn (?float $value): string => $value === null ? '—' : number_format($value, 2).'x';

        $definitions = [
            ['Status', fn (Ipo $ipo) => $ipo->statusLabel()],
            ['Board', fn (Ipo $ipo) => $ipo->typeLabel().' · '.$ipo->exchangeLabel()],
            ['Subscription dates', fn (Ipo $ipo) => $ipo->open_date
                ? $ipo->open_date->format('j M').' – '.($ipo->close_date ?? $ipo->open_date)->format('j M Y')
                : 'Awaited'],
            ['Listing date', fn (Ipo $ipo) => $ipo->listing_date?->format('D, j M Y') ?? 'Awaited'],
            ['Price band', fn (Ipo $ipo) => $ipo->priceBand()],
            ['Lot size', fn (Ipo $ipo) => $ipo->lot_size ? number_format($ipo->lot_size).' shares' : '—'],
            ['Min. investment', fn (Ipo $ipo) => Ipo::money($ipo->lotTable()[0]['amount'] ?? null)],
            ['Issue size', fn (Ipo $ipo) => $cr($ipo->issue_size)],
            ['GMP', fn (Ipo $ipo) => $ipo->hasGmp() ? ($ipo->gmp > 0 ? '+' : '').'₹'.Ipo::num($ipo->gmp) : '—', fn (Ipo $ipo) => $ipo->gmpPercent(), 'high'],
            ['Est. listing gain', fn (Ipo $ipo) => $pct($ipo->gmpPercent()), fn (Ipo $ipo) => $ipo->gmpPercent(), 'high'],
            ['Subscription (total)', fn (Ipo $ipo) => $times($ipo->subscription_total), fn (Ipo $ipo) => $ipo->subscription_total, 'high'],
            ['Listing gain', fn (Ipo $ipo) => $pct($ipo->listingGainPercent()), fn (Ipo $ipo) => $ipo->listingGainPercent(), 'high'],
            ['Registrar', fn (Ipo $ipo) => $ipo->registrar ?: '—'],
            ['Fresh issue / OFS', fn (Ipo $ipo) => $ipo->detail ? $cr($ipo->detail->fresh_issue_cr).' / '.$cr($ipo->detail->ofs_cr) : '—'],
            ['Latest revenue', fn (Ipo $ipo) => $latest($ipo) ? $cr($latest($ipo)->revenue).' ('.$latest($ipo)->period.')' : '—', fn (Ipo $ipo) => $latest($ipo)?->revenue, 'high'],
            ['Latest PAT', fn (Ipo $ipo) => $latest($ipo) ? $cr($latest($ipo)->pat) : '—', fn (Ipo $ipo) => $latest($ipo)?->pat, 'high'],
            ['PAT margin', fn (Ipo $ipo) => $pct($latest($ipo)?->patMargin()), fn (Ipo $ipo) => $latest($ipo)?->patMargin(), 'high'],
            ['P/E (post issue)', fn (Ipo $ipo) => $ipo->detail?->pe_post !== null ? Ipo::num($ipo->detail->pe_post).'x' : '—', fn (Ipo $ipo) => $ipo->detail?->pe_post, 'low'],
            ['ROE', fn (Ipo $ipo) => $pct($ipo->detail?->roe), fn (Ipo $ipo) => $ipo->detail?->roe, 'high'],
            ['Debt / Equity', fn (Ipo $ipo) => $ipo->detail?->debt_equity !== null ? Ipo::num($ipo->detail->debt_equity) : '—', fn (Ipo $ipo) => $ipo->detail?->debt_equity, 'low'],
        ];

        return array_map(fn (array $definition): array => [
            'label' => $definition[0],
            'cells' => $ipos->map($definition[1])->all(),
            'best' => isset($definition[2]) ? $this->bestColumns($ipos, $definition[2], $definition[3]) : [],
        ], $definitions);
    }

    /**
     * Columns holding the best value; empty when fewer than two IPOs have a value or all are tied.
     * For "lower is better" ratios a negative value signals losses or negative equity, so it never wins.
     *
     * @param  Collection<int, Ipo>  $ipos
     * @param  Closure(Ipo): (float|null)  $metric
     * @return array<int, int>
     */
    private function bestColumns(Collection $ipos, Closure $metric, string $direction): array
    {
        $values = $ipos->map($metric)->filter(fn (?float $value): bool => $value !== null && ($direction === 'high' || $value >= 0));
        if ($values->count() < 2 || $values->unique()->count() === 1) {
            return [];
        }

        $best = $direction === 'low' ? $values->min() : $values->max();

        return $values->filter(fn (float $value): bool => $value === $best)->keys()->all();
    }
}
