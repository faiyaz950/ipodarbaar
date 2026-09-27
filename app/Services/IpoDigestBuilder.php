<?php

namespace App\Services;

use App\Models\Ipo;
use App\Models\IpoGmpHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Collects "what's happening with IPOs" for the email digest and Telegram posts.
 */
class IpoDigestBuilder
{
    public function __construct(private NewsService $news) {}

    /**
     * Email digest content as plain arrays, so it can travel inside queued jobs.
     *
     * @return array{date: string, frequency: string, subject: string, sections: array<int, array{title: string, items: array<int, array<string, mixed>>}>, news: array<int, array{headline: string, url: string, category: string}>, is_empty: bool}
     */
    public function email(string $frequency): array
    {
        $open = Ipo::open()->orderBy('close_date')->get();
        $opening = $this->openingWithin(7);
        $changes = $this->gmpChanges($open->merge($opening));

        $sections = [
            [
                'title' => 'Open for subscription',
                'items' => $open->map(fn (Ipo $ipo): array => $this->item($ipo, 'Closes '.$ipo->close_date?->format('D, j M'), $changes[$ipo->id] ?? null))->all(),
            ],
            [
                'title' => 'Opening soon',
                'items' => $opening->map(fn (Ipo $ipo): array => $this->item($ipo, 'Opens '.$ipo->open_date?->format('D, j M'), $changes[$ipo->id] ?? null))->all(),
            ],
        ];

        if ($frequency === 'weekly') {
            $sections[] = [
                'title' => 'Listing this week',
                'items' => $this->listingWithin(7)->map(fn (Ipo $ipo): array => $this->item($ipo, 'Lists '.$ipo->listing_date?->format('D, j M')))->all(),
            ];
            $sections[] = [
                'title' => "Last week's listings",
                'items' => $this->listedWithin(7)->map(fn (Ipo $ipo): array => $this->item($ipo, 'Listed '.$ipo->listing_date?->format('D, j M')))->all(),
            ];
        } else {
            $sections[] = [
                'title' => 'Allotment & listing',
                'items' => $this->allotmentAndListing(3)->all(),
            ];
        }

        $hasEvents = collect($sections)->contains(fn (array $section): bool => $section['items'] !== []);
        $sections = array_values(array_filter($sections, fn (array $section): bool => $section['items'] !== []));

        $news = collect($this->news->latest($frequency === 'weekly' ? 5 : 3)['items'])
            ->map(fn (array $item): array => [
                'headline' => $this->markdownSafe($item['headline']),
                'url' => $item['url'],
                'category' => $item['category']['name'],
            ])
            ->all();

        return [
            'date' => today()->toDateString(),
            'frequency' => $frequency,
            'subject' => $this->subject($frequency, $open->count(), $opening->count()),
            'sections' => $sections,
            'news' => $news,
            'is_empty' => ! $hasEvents,
        ];
    }

    /**
     * Key events happening on a given day, for the Telegram morning post.
     *
     * @return array{opening: Collection<int, Ipo>, closing: Collection<int, Ipo>, allotment: Collection<int, Ipo>, listing: Collection<int, Ipo>, tomorrow: Collection<int, Ipo>}
     */
    public function day(Carbon $day): array
    {
        $date = $day->toDateString();
        $tomorrow = $day->copy()->addDay()->toDateString();

        return [
            'opening' => Ipo::query()->whereDate('open_date', $date)->orderBy('name')->get(),
            'closing' => Ipo::query()->whereDate('close_date', $date)->whereDate('open_date', '<', $date)->orderBy('name')->get(),
            'allotment' => Ipo::closed()->get()
                ->filter(fn (Ipo $ipo): bool => $this->allotmentDate($ipo)?->isSameDay($day) ?? false)
                ->values(),
            'listing' => Ipo::query()->whereDate('listing_date', $date)->orderBy('name')->get(),
            'tomorrow' => Ipo::query()->whereDate('open_date', $tomorrow)->orderBy('name')->get(),
        ];
    }

    /**
     * Active IPOs with a GMP, highest expected gain first, with the change since the
     * previous recorded day.
     *
     * @return Collection<int, array{ipo: Ipo, change: float|null}>
     */
    public function gmpBoard(int $limit = 15): Collection
    {
        $ipos = Ipo::active()->whereNotNull('gmp')->get()
            ->sortByDesc(fn (Ipo $ipo): float => $ipo->gmpPercent() ?? 0)
            ->take($limit)
            ->values();
        $changes = $this->gmpChanges($ipos);

        return $ipos->map(fn (Ipo $ipo): array => ['ipo' => $ipo, 'change' => $changes[$ipo->id] ?? null]);
    }

    /**
     * GMP change between today's recorded value and the previous recorded day.
     *
     * @param  Collection<int, Ipo>  $ipos
     * @return array<int, float>
     */
    public function gmpChanges(Collection $ipos): array
    {
        if ($ipos->isEmpty()) {
            return [];
        }

        $histories = IpoGmpHistory::query()
            ->whereIn('ipo_id', $ipos->pluck('id'))
            ->whereDate('date', '>=', today()->subDays(10)->toDateString())
            ->orderBy('date')
            ->get(['ipo_id', 'date', 'gmp'])
            ->groupBy('ipo_id');

        $changes = [];
        foreach ($histories as $ipoId => $history) {
            $latest = $history->last();
            $previous = $history->count() > 1 ? $history->get($history->count() - 2) : null;

            if ($previous && $latest->date->isToday() && $latest->gmp !== null && $previous->gmp !== null) {
                $changes[(int) $ipoId] = round($latest->gmp - $previous->gmp, 2);
            }
        }

        return $changes;
    }

    public function allotmentDate(Ipo $ipo): ?Carbon
    {
        return collect($ipo->timeline())->firstWhere('label', 'Basis of Allotment')['date'] ?? null;
    }

    /**
     * @return Collection<int, Ipo>
     */
    private function openingWithin(int $days): Collection
    {
        return Ipo::upcoming()
            ->whereNotNull('open_date')
            ->whereDate('open_date', '<=', today()->addDays($days)->toDateString())
            ->orderBy('open_date')
            ->get();
    }

    /**
     * @return Collection<int, Ipo>
     */
    private function listingWithin(int $days): Collection
    {
        return Ipo::query()
            ->whereBetween('listing_date', [today()->toDateString(), today()->addDays($days)->toDateString()])
            ->orderBy('listing_date')
            ->get();
    }

    /**
     * @return Collection<int, Ipo>
     */
    private function listedWithin(int $days): Collection
    {
        return Ipo::listed()
            ->whereNotNull('listing_date')
            ->whereDate('listing_date', '>=', today()->subDays($days)->toDateString())
            ->orderByDesc('listing_date')
            ->get();
    }

    /**
     * Closed IPOs whose allotment or listing falls within the next few days.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function allotmentAndListing(int $days): Collection
    {
        $until = today()->addDays($days);

        return Ipo::closed()->orderBy('listing_date')->get()
            ->map(function (Ipo $ipo) use ($until): ?array {
                $allotment = $this->allotmentDate($ipo);

                if ($allotment && $allotment->gte(today()) && $allotment->lte($until)) {
                    return $this->item($ipo, 'Allotment '.$allotment->format('D, j M').' (tentative)');
                }
                if ($ipo->listing_date && $ipo->listing_date->lte($until)) {
                    return $this->item($ipo, 'Lists '.$ipo->listing_date->format('D, j M'));
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{name: string, url: string, type: string, price: string, gmp: float|null, gmp_pct: float|null, change: float|null, note: string|null, listing_gain_pct: float|null}
     */
    private function item(Ipo $ipo, ?string $note = null, ?float $change = null): array
    {
        return [
            'name' => $this->markdownSafe($ipo->name),
            'url' => $ipo->url(),
            'type' => $ipo->typeLabel(),
            'price' => $ipo->priceBand(),
            'gmp' => $ipo->gmp,
            'gmp_pct' => $ipo->gmpPercent(),
            'change' => $change,
            'note' => $note,
            'listing_gain_pct' => $ipo->listingGainPercent(),
        ];
    }

    /**
     * Keeps text from breaking the digest's markdown tables and links.
     */
    private function markdownSafe(string $text): string
    {
        return str_replace(['|', '[', ']'], ['/', '(', ')'], $text);
    }

    private function subject(string $frequency, int $open, int $opening): string
    {
        $parts = [];
        if ($open > 0) {
            $parts[] = $open.' '.($open === 1 ? 'IPO' : 'IPOs').' open';
        }
        if ($opening > 0) {
            $parts[] = $opening.' opening soon';
        }
        $lead = $parts ? implode(', ', $parts) : ($frequency === 'weekly' ? 'Your weekly IPO roundup' : 'Your IPO update');

        return $lead.' · IPO Darbaar '.($frequency === 'weekly' ? 'weekly' : 'daily').', '.today()->format('j M');
    }
}
