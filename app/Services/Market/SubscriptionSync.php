<?php

namespace App\Services\Market;

use App\Models\Ipo;
use App\Models\IpoSubscriptionDay;
use App\Support\PageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Copies category-wise subscription from NSE onto IPOs while they take bids (and for a few
 * days after, to catch the final figures), keeping the last reading of each bidding day.
 * NSE's figures cover bids on both exchanges; issues listed only on BSE SME are not covered.
 */
class SubscriptionSync
{
    /** Keep reading an issue for this many days after it closes, for the final figures. */
    private const AFTER_CLOSE_DAYS = 3;

    public function __construct(private NseClient $nse) {}

    /**
     * @return array{issues: int, linked: int, updated: int}
     */
    public function run(): array
    {
        $today = now()->startOfDay();
        $ipos = Ipo::query()
            ->whereNotNull('open_date')
            ->whereDate('open_date', '<=', $today->toDateString())
            ->where(fn ($q) => $q->whereNull('close_date')->orWhereDate('close_date', '>=', $today->copy()->subDays(self::AFTER_CLOSE_DAYS)->toDateString()))
            ->get();

        $issues = $this->nse->currentIssues();
        $linked = $this->link($ipos, $issues);

        $updated = 0;
        foreach ($ipos->filter(fn (Ipo $ipo): bool => filled($ipo->nse_symbol)) as $ipo) {
            $updated += (int) $this->update($ipo);
        }

        if ($updated > 0) {
            PageCache::flush();
        }

        return ['issues' => count($issues), 'linked' => $linked, 'updated' => $updated];
    }

    /**
     * Finds NSE symbols for IPOs that opened since $since from NSE's past issues, then fetches
     * the final subscription for those that have none yet.
     *
     * @return array{linked: int, updated: int}
     */
    public function backfill(Carbon $since): array
    {
        $ipos = Ipo::query()->whereNotNull('open_date')->whereDate('open_date', '>=', $since->toDateString())->get();
        $past = array_filter($this->nse->pastIssues(), fn (array $issue): bool => $issue['start'] === null || $issue['start']->gte($since->copy()->subDays(3)));
        $linked = $this->link($ipos, array_values($past));

        $updated = 0;
        $pending = $ipos->filter(fn (Ipo $ipo): bool => filled($ipo->nse_symbol) && $ipo->subscription_total === null && $ipo->close_date?->lt(now()->startOfDay()));
        foreach ($pending as $ipo) {
            $updated += (int) $this->update($ipo);
            usleep(250_000);
        }

        if ($updated > 0) {
            PageCache::flush();
        }

        return ['linked' => $linked, 'updated' => $updated];
    }

    public function update(Ipo $ipo): bool
    {
        $figures = $this->nse->subscription((string) $ipo->nse_symbol);
        if ($figures === null) {
            return false;
        }

        $asOf = $figures['as_of'] ?? now();
        // A reading older than the issue itself belongs to an earlier issue with the same symbol.
        if ($ipo->open_date && $asOf->lt($ipo->open_date->copy()->startOfDay())) {
            return false;
        }

        $values = [
            'subscription_qib' => $figures['qib'], 'subscription_nii' => $figures['nii'], 'subscription_bnii' => $figures['bnii'],
            'subscription_snii' => $figures['snii'], 'subscription_retail' => $figures['retail'],
            'subscription_employee' => $figures['employee'], 'subscription_total' => $figures['total'],
        ];
        $ipo->forceFill($values);
        if (! $ipo->isDirty() && $ipo->subscription_updated_at?->gte($asOf)) {
            return false;
        }
        $ipo->subscription_updated_at = $asOf;
        $ipo->save();

        IpoSubscriptionDay::query()->updateOrCreate(
            ['ipo_id' => $ipo->id, 'date' => $asOf->toDateString()],
            [...array_intersect_key($figures, array_flip(IpoSubscriptionDay::CATEGORIES)), 'as_of' => $asOf, 'source' => 'nse'],
        );

        return true;
    }

    /**
     * @param  Collection<int, Ipo>  $ipos
     * @param  list<array{symbol: string, name: string, start: ?Carbon}>  $issues
     */
    private function link(Collection $ipos, array $issues): int
    {
        $linked = 0;
        foreach ($issues as $issue) {
            if ($ipos->contains(fn (Ipo $ipo): bool => $ipo->nse_symbol === $issue['symbol'])) {
                continue;
            }
            $ipo = $ipos->first(fn (Ipo $ipo): bool => blank($ipo->nse_symbol)
                && CompanyName::matches($ipo->name, $issue['name'])
                && ($issue['start'] === null || $ipo->open_date === null || abs($ipo->open_date->diffInDays($issue['start'])) <= 3));
            if ($ipo) {
                $ipo->nse_symbol = $issue['symbol'];
                $ipo->save();
                $linked++;
            }
        }

        return $linked;
    }
}
