<?php

namespace App\Services\Market;

use App\Models\Ipo;
use App\Models\IpoGmpHistory;
use App\Services\IpoStatsService;
use App\Support\PageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Records each IPO's listing price (the first trade on listing day) and listing-day close:
 * from NSE's special pre-open session at 10 AM for mainboard listings, and from the NSE and
 * BSE bhavcopies after the market closes for every listing, SME included. A price an editor
 * entered is never overwritten. When an IPO turns out to have listed on a different day than
 * the data said, the date is corrected and locked against the API sync.
 */
class ListingPriceSync
{
    /** How far the listing day's previous close may be from our issue price and still count as listing day. */
    private const ISSUE_PRICE_TOLERANCE = 0.03;

    /** Look for a listing up to this many days after the issue closed. */
    private const MAX_DAYS_AFTER_CLOSE = 14;

    public function __construct(private NseClient $nse, private Bhavcopy $bhavcopy, private IpoStatsService $stats) {}

    /** Today's mainboard listings from the special pre-open session. */
    public function morning(): int
    {
        $rows = $this->nse->preOpenListings();
        if ($rows === []) {
            return 0;
        }

        $recorded = 0;
        foreach ($this->candidates(now()->startOfDay(), 'listing_price') as $ipo) {
            $row = collect($rows)->first(fn (array $row): bool => ($row['symbol'] === $ipo->nse_symbol || ($row['isin'] !== '' && $row['isin'] === $ipo->isin))
                && $this->isIssuePrice($ipo, $row['prev_close']));
            if ($row) {
                $this->record($ipo, now()->startOfDay(), 'NSE', $row['price'], null, ['nse_symbol' => $row['symbol'], 'isin' => $row['isin']]);
                $recorded++;
            }
        }

        return $this->done($recorded);
    }

    /** Listing price and close from the day's bhavcopies, for IPOs that listed on $date. */
    public function endOfDay(Carbon $date): int
    {
        $date = $date->copy()->startOfDay();
        if ($date->isWeekend()) {
            return 0;
        }

        $candidates = $this->candidates($date, 'listing_close');
        if ($candidates->isEmpty()) {
            return 0;
        }

        $nse = $this->bhavcopy->rows('NSE', $date);
        $bse = $this->bhavcopy->rows('BSE', $date);
        $recorded = 0;
        foreach ($candidates as $ipo) {
            $nseRow = $this->nseRow($ipo, $nse);
            $bseRow = $this->bseRow($ipo, $bse);
            // The exchange whose price was already recorded wins, then NSE (the usual reference), then BSE.
            $row = match (true) {
                $ipo->listing_exchange === 'BSE' && $bseRow !== null => $bseRow,
                $nseRow !== null => $nseRow,
                default => $bseRow,
            };
            if (! $row) {
                continue;
            }
            $this->record($ipo, $date, $row['exchange'], $row['open'], $row['close'], array_filter([
                'nse_symbol' => $nseRow['symbol'] ?? null,
                'bse_code' => $bseRow['code'] ?? null,
                'isin' => $row['isin'] ?: null,
            ]));
            $recorded++;
        }

        return $this->done($recorded);
    }

    /** Fills listing prices for IPOs that closed between $since and $until, one trading day at a time. */
    public function backfill(Carbon $since, Carbon $until): int
    {
        $recorded = 0;
        for ($day = $since->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            if (! $day->isWeekend()) {
                $recorded += $this->endOfDay($day);
                $this->bhavcopy->forget();
            }
        }

        return $recorded;
    }

    /**
     * IPOs that closed before $date, recently enough to be listing on it, still missing $field.
     *
     * @return Collection<int, Ipo>
     */
    private function candidates(Carbon $date, string $field): Collection
    {
        return Ipo::query()
            ->whereNull($field)
            ->whereNotNull('close_date')
            ->whereDate('close_date', '<', $date->toDateString())
            ->whereDate('close_date', '>=', $date->copy()->subDays(self::MAX_DAYS_AFTER_CLOSE)->toDateString())
            ->where(fn ($q) => $q->whereNull('listing_date')->orWhereDate('listing_date', '>=', $date->copy()->subDays(3)->toDateString()))
            ->get();
    }

    /**
     * On NSE a new listing's "previous close" is its issue price.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function nseRow(Ipo $ipo, Collection $rows): ?array
    {
        return $rows->first(fn (array $row): bool => $this->isIssuePrice($ipo, $row['prev_close'])
            && ($ipo->nse_symbol ? $row['symbol'] === $ipo->nse_symbol : CompanyName::matches($row['name'], $ipo->name)));
    }

    /**
     * On BSE a new listing has no previous close. A name match also has to open at a believable
     * price for the issue, so a different new listing with a similar name isn't taken.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function bseRow(Ipo $ipo, Collection $rows): ?array
    {
        return $rows->first(fn (array $row): bool => (float) $row['prev_close'] === 0.0 && $row['open'] > 0
            && (! $ipo->price || ($row['open'] >= $ipo->price * 0.3 && $row['open'] <= $ipo->price * 3))
            && (($ipo->bse_code && $row['code'] === $ipo->bse_code) || ($ipo->isin && $row['isin'] === $ipo->isin) || CompanyName::matches($row['name'], $ipo->name)));
    }

    private function isIssuePrice(Ipo $ipo, ?float $price): bool
    {
        return $price !== null && $ipo->price > 0 && abs($price - $ipo->price) <= $ipo->price * self::ISSUE_PRICE_TOLERANCE;
    }

    /**
     * @param  array<string, string|null>  $ids  Exchange identifiers to remember (only filled when empty).
     */
    private function record(Ipo $ipo, Carbon $date, string $exchange, float $open, ?float $close, array $ids): void
    {
        $ipo->listing_price ??= round($open, 2);
        $ipo->listing_exchange ??= $exchange;
        $ipo->listing_gmp ??= $this->gmpBeforeListing($ipo, $date);
        if ($close !== null && $close > 0) {
            $ipo->listing_close ??= round($close, 2);
        }
        foreach ($ids as $field => $value) {
            if ($value && blank($ipo->{$field})) {
                $ipo->{$field} = $value;
            }
        }
        if (! $ipo->listing_date?->isSameDay($date)) {
            $ipo->listing_date = $date->copy();
            $ipo->locked_fields = array_values(array_unique([...($ipo->locked_fields ?? []), 'listing_date']));
        }
        $ipo->save();
    }

    /** The last daily GMP recorded before listing day, else the current one (listing day's feed may already show the result). */
    private function gmpBeforeListing(Ipo $ipo, Carbon $date): ?float
    {
        $before = IpoGmpHistory::query()->where('ipo_id', $ipo->id)->whereDate('date', '<', $date->toDateString())
            ->whereNotNull('gmp')->orderByDesc('date')->value('gmp');

        return $before !== null ? (float) $before : $ipo->gmp;
    }

    private function done(int $recorded): int
    {
        if ($recorded > 0) {
            PageCache::flush();
            $this->stats->flush();
        }

        return $recorded;
    }
}
