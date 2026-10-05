<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use App\Models\Ipo;
use App\Models\IpoFinancial;
use App\Services\IpoDigestBuilder;
use App\Support\IpoInsights;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Writes the automatic blog posts from IPO Darbaar's own data: a daily "IPO today" update
 * and a weekly calendar. Every figure comes from the database; the text only describes and
 * compares those figures (growth rates, GMP premiums, issue structure), so nothing is invented.
 *
 * @phpstan-type Draft array{slug: string, category: string, title: string, seo_title: string, excerpt: string, seo_description: string, takeaways: string, body: string, image: array{path: string, alt: string}|null, ipo_ids: list<int>}
 */
class AutoBlogWriter
{
    /** IPOs whose bidding window is longer than this are treated as bad data and left out. */
    private const MAX_BIDDING_DAYS = 12;

    public function __construct(private IpoDigestBuilder $digest, private BlogImageService $images) {}

    /**
     * The daily update, or null at weekends and when nothing is open, closing, opening, listing or being allotted.
     *
     * @return Draft|null
     */
    public function daily(Carbon $day): ?array
    {
        $day = $day->copy()->startOfDay();
        if ($day->isWeekend()) {
            return null;
        }
        $date = $day->toDateString();
        $all = $this->ipos()->filter(fn (Ipo $ipo): bool => $ipo->open_date || $ipo->listing_date);

        $closing = $all->filter(fn (Ipo $ipo): bool => $ipo->close_date?->toDateString() === $date && $ipo->open_date?->lt($day))->sortByDesc('issue_size')->values();
        $opening = $all->filter(fn (Ipo $ipo): bool => $ipo->open_date?->toDateString() === $date)->sortByDesc('issue_size')->values();
        $listing = $all->filter(fn (Ipo $ipo): bool => $ipo->listing_date?->toDateString() === $date)->sortByDesc('issue_size')->values();
        $allotment = $all->filter(fn (Ipo $ipo): bool => $ipo->close_date?->lt($day) && ($this->digest->allotmentDate($ipo)?->isSameDay($day) ?? false))->values();
        $stillOpen = $all->filter(fn (Ipo $ipo): bool => $ipo->open_date?->lt($day) && $ipo->close_date?->gt($day))->sortBy('close_date')->values();
        $upcoming = $all->filter(fn (Ipo $ipo): bool => $ipo->open_date?->gt($day) && $ipo->open_date->lte($day->copy()->addDays(7)))->sortBy('open_date')->values();
        // The most recent listing day before today (holidays skipped), once listing prices are known.
        $recent = $all->filter(fn (Ipo $ipo): bool => $ipo->listing_price !== null && $ipo->listing_date?->lt($day) && $ipo->listing_date->gte($day->copy()->subDays(5)));
        $lastDay = $recent->max('listing_date') ?? $day->copy()->subDay();
        $listedLast = $recent->filter(fn (Ipo $ipo): bool => $ipo->listing_date->isSameDay($lastDay))
            ->sortByDesc(fn (Ipo $ipo): float => $ipo->listingGainPercent() ?? -1000)->values();

        if ($closing->isEmpty() && $opening->isEmpty() && $listing->isEmpty() && $allotment->isEmpty() && $stillOpen->isEmpty()) {
            return null;
        }

        $label = $day->format('j M Y');
        $long = $day->format('l, j F Y');
        $slug = $this->dailySlug($day);
        $board = $this->gmpLeaders($closing->merge($opening)->merge($stillOpen)->merge($upcoming));
        $top = $board->first();

        $counts = array_filter([
            'closing' => $closing->count(), 'listing' => $listing->count(), 'opening' => $opening->count(),
        ]);
        $title = 'IPO Today ('.$label.'): '.($counts === []
            ? $stillOpen->count().' '.$this->ipoWord($stillOpen->count()).' Open, GMP Update'
            : implode(', ', array_map(fn (string $key, int $n): string => match ($key) {
                'closing' => $n.' '.$this->ipoWord($n).' Closing',
                'listing' => $n.' Listing',
                'opening' => $n.' Opening',
            }, array_keys($counts), $counts)));

        $summary = $this->joinClauses(array_filter([
            $closing->isNotEmpty() ? $this->countWord($closing->count(), 'IPO').' '.($closing->count() === 1 ? 'takes its' : 'take their').' last bids today' : null,
            $listing->isNotEmpty() ? $this->countWord($listing->count(), 'company', 'companies').' '.($listing->count() === 1 ? 'lists' : 'list').' on the stock exchanges' : null,
            $opening->isNotEmpty() ? $this->countWord($opening->count(), 'new issue').' '.($opening->count() === 1 ? 'opens' : 'open').' for bidding' : null,
            $allotment->isNotEmpty() ? 'the basis of allotment is expected for '.$this->countWord($allotment->count(), 'IPO') : null,
        ]));

        $cover = $this->images->cover($slug.'-cover', 'Daily IPO update', $title, $long.' · price bands, GMP, financials and dates', array_values(array_filter([
            $closing->isNotEmpty() ? [(string) $closing->count(), 'Closing today'] : null,
            $listing->isNotEmpty() ? [(string) $listing->count(), 'Listing today'] : null,
            $opening->isNotEmpty() ? [(string) $opening->count(), 'Opening today'] : null,
            $stillOpen->isNotEmpty() ? [(string) $stillOpen->count(), 'Still open'] : null,
            $top ? [$this->percent($top->gmpPercent()), 'Top GMP: '.$this->shortName($top)] : null,
        ])), $closing->merge($opening)->merge($listing)->unique('id'));

        $html = [];
        $html[] = '<p>'.e($long).'. '.($closing->count() + $listing->count() + $opening->count() >= 8 ? 'It is a busy day in the primary market: ' : 'Here is what is happening in the IPO market today: ')
            .e($summary !== '' ? $summary : $this->countWord($stillOpen->count(), 'IPO').' '.($stillOpen->count() === 1 ? 'is' : 'are').' open for bidding').'.</p>';
        $html[] = '<p>For each issue you will find the price band, lot size, grey market premium (GMP), financials and key dates, with links to the live IPO pages. Figures are as of '.now()->format('g:i A').' IST; GMP and subscription change through the day, so check the IPO page before you apply.</p>';

        $html[] = '<h2>Today at a glance</h2>';
        $html[] = $this->table(['Event', 'IPOs'], array_values(array_filter([
            $closing->isNotEmpty() ? ['Last day to apply', $this->linkList($closing)] : null,
            $opening->isNotEmpty() ? ['Opening today', $this->linkList($opening)] : null,
            $listing->isNotEmpty() ? ['Listing today', $this->linkList($listing)] : null,
            $allotment->isNotEmpty() ? ['Allotment expected', $this->linkList($allotment)] : null,
            $stillOpen->isNotEmpty() ? ['Still open', $this->linkList($stillOpen)] : null,
        ])));

        if ($closing->isNotEmpty()) {
            $html[] = '<h2>IPOs closing today</h2>';
            $html[] = '<p>Today is the last day to bid for '.$this->countWord($closing->count(), 'IPO').'. Applications through UPI usually have to be placed and the mandate approved before the bank cut-off in the afternoon, so do not leave it to the last minute.</p>';
            $main = $closing->filter->isMainboard();
            foreach ($main as $ipo) {
                $html[] = $this->profile($ipo);
            }
            $sme = $closing->reject->isMainboard();
            if ($sme->isNotEmpty()) {
                if ($main->isNotEmpty()) {
                    $html[] = '<h3>SME IPOs closing today</h3>';
                }
                $html[] = $this->table(['IPO', 'Price band', 'Lot value', 'Issue size', 'GMP', 'Subscribed', 'Listing'], $sme->map(fn (Ipo $ipo): array => [
                    $this->link($ipo), $ipo->priceBand(), $this->lotValue($ipo), $this->size($ipo), $this->gmpText($ipo), $this->times($ipo->subscription_total), $this->date($ipo->listing_date),
                ])->all());
            }
        }

        if ($opening->isNotEmpty()) {
            $html[] = '<h2>IPOs opening today</h2>';
            $html[] = '<p>'.($opening->count() === 1 ? 'One issue opens' : $this->countWord($opening->count(), 'issue').' open').' for bidding today. Here is what each company does, how much it is raising and what its numbers look like.</p>';
            foreach ($opening as $ipo) {
                $html[] = $this->profile($ipo);
            }
        }

        if ($listing->isNotEmpty()) {
            $html[] = '<h2>IPOs listing today</h2>';
            $html[] = '<p>The table compares each issue price with the price the grey market suggests. These are estimates: the actual listing price is set by orders on the exchange in the pre-open session and can be well above or below the GMP.</p>';
            $html[] = $this->table(['IPO', 'Board', 'Issue price', 'GMP', 'Expected listing', 'Expected gain'], $listing->map(fn (Ipo $ipo): array => [
                $this->link($ipo), $ipo->typeLabel(), $ipo->price ? Ipo::money($ipo->price) : '—', $this->gmpText($ipo),
                $ipo->estListingPrice() !== null ? Ipo::money($ipo->estListingPrice()) : '—',
                $ipo->gmpPercent() !== null ? $this->percent($ipo->gmpPercent()) : '—',
            ])->all());
            $html[] = '<p>Once trading starts, the <a href="/ipo-listing-today">IPO listing today</a> page shows the actual listing price next to the GMP estimate. If you were allotted shares, our guide on <a href="/ipo-guide/how-to-sell-ipo-shares-on-listing-day">selling IPO shares on listing day</a> explains the options.</p>';
        }

        if ($allotment->isNotEmpty()) {
            $html[] = '<h2>Allotment status expected today</h2>';
            $html[] = '<p>The basis of allotment is expected today for the IPOs below (one working day after they closed). The registrar usually publishes the status in the evening; shares are credited to demat accounts and unallotted money is released on the next working day.</p>';
            $html[] = $this->table(['IPO', 'Registrar', 'Closed', 'Listing'], $allotment->map(fn (Ipo $ipo): array => [
                $this->link($ipo), $this->registrar($ipo), $this->date($ipo->close_date), $this->date($ipo->listing_date),
            ])->all());
            $html[] = '<p>See <a href="/ipo-allotment-status">IPO allotment status</a> for registrar links, or read <a href="/ipo-guide/how-to-check-ipo-allotment-status">how to check IPO allotment status</a>.</p>';
        }

        if ($listedLast->isNotEmpty()) {
            $html[] = '<h2>How the last listings did</h2>';
            $html[] = '<p>The '.e($this->countWord($listedLast->count(), 'IPO')).' that listed on '.e($lastDay->format('l, j M')).', with the opening (listing) price and the close that day against the issue price, from the NSE and BSE price files.</p>';
            $html[] = $this->table(['IPO', 'Issue price', 'Listing price', 'Listing gain', 'Day-1 close', 'Close vs issue'], $listedLast->map(fn (Ipo $ipo): array => [
                $this->link($ipo), Ipo::money($ipo->price), Ipo::money($ipo->listing_price), $ipo->listingGainPercent() !== null ? $this->percent($ipo->listingGainPercent()) : '—',
                $ipo->listing_close ? Ipo::money($ipo->listing_close) : '—', $ipo->listingCloseGainPercent() !== null ? $this->percent($ipo->listingCloseGainPercent()) : '—',
            ])->all());
            $recap = BlogPost::query()->live()->where('slug', $this->listingSlug($lastDay))->first();
            if ($recap) {
                $html[] = '<p>Full story, with GMP against the actual listing for each IPO: <a href="'.e(route('blog.show', $recap->slug, false)).'">'.e($recap->title).'</a>.</p>';
            }
            $best = $listedLast->filter(fn (Ipo $ipo): bool => $ipo->listingGainPercent() !== null)->sortByDesc(fn (Ipo $ipo): float => $ipo->listingGainPercent())->first();
            if ($best) {
                $html[] = '<p>'.e($best->name).' had the strongest debut, opening '.e($this->percent($best->listingGainPercent())).' above its issue price'
                    .($best->hasGmp() && $best->gmpPercent() !== null ? ' against a last GMP of '.e($this->percent($best->gmpPercent())) : '').'.</p>';
            }
        }

        if ($stillOpen->isNotEmpty()) {
            $html[] = '<h2>Other IPOs open for bidding</h2>';
            $html[] = $this->table(['IPO', 'Board', 'Price band', 'Closes', 'GMP', 'Subscribed'], $stillOpen->map(fn (Ipo $ipo): array => [
                $this->link($ipo), $ipo->typeLabel(), $ipo->priceBand(), $this->date($ipo->close_date), $this->gmpText($ipo), $this->times($ipo->subscription_total),
            ])->all());
        }

        if ($board->isNotEmpty()) {
            $chart = $this->images->gmpChart($slug.'-gmp', 'Grey market premium today', 'GMP as a share of the upper price band for open and upcoming IPOs, highest first', $board,
                'GMP is unofficial · as of '.now()->format('g:i A').', '.$label);
            $html[] = '<h2>GMP today: open and upcoming IPOs</h2>';
            $html[] = '<p>'.e($top->name).' has the highest grey market premium today at '.e($this->gmpText($top)).'. A high GMP shows strong demand in the unofficial market, but it is not a guarantee: it can fall sharply before listing, especially when many issues compete for money in the same week. Read <a href="/ipo-guide/what-is-ipo-gmp">what is IPO GMP</a> to see how it works.</p>';
            $html[] = $this->figure($chart, 'IPO GMP today, '.$label, 'Grey market premium of open and upcoming IPOs on '.$label.'. Live figures: IPO GMP page.');
            $html[] = '<p>The full, live table is on the <a href="/ipo-gmp">IPO GMP today</a> page.</p>';
        }

        if ($upcoming->isNotEmpty()) {
            $html[] = '<h2>Opening in the next few days</h2>';
            $html[] = $this->table(['IPO', 'Board', 'Opens', 'Closes', 'Price band', 'Issue size'], $upcoming->map(fn (Ipo $ipo): array => [
                $this->link($ipo), $ipo->typeLabel(), $this->date($ipo->open_date), $this->date($ipo->close_date), $ipo->priceBand(), $this->size($ipo),
            ])->all());
            $html[] = '<p>Add any of these to your phone calendar from its IPO page, or see every date on the <a href="/ipo-calendar">IPO calendar</a>.</p>';
        }

        $html[] = $this->faqs(array_values(array_filter([
            $closing->isNotEmpty() ? ['Which IPOs close today, '.$label.'?', 'Today is the last day to apply for '.$this->nameList($closing).'.'] : null,
            $listing->isNotEmpty() ? ['Which IPOs list today?', $this->nameList($listing).' '.($listing->count() === 1 ? 'lists' : 'list').' on the exchanges today.'
                .($this->bestListing($listing) ? ' Based on the GMP, '.$this->bestListing($listing).'.' : '')] : null,
            $top ? ['Which IPO has the highest GMP today?', $top->name.' has the highest GMP among open and upcoming IPOs at '.$this->gmpText($top).', which points to a listing around '.Ipo::money($top->estListingPrice()).' against an upper price of '.Ipo::money($top->price).'. GMP is unofficial and can change.'] : null,
            ['How do I check my IPO allotment status?', 'After the basis of allotment, check on the registrar\'s website with your PAN or application number, or on the BSE and NSE websites. Our IPO allotment status page links to each registrar.'],
        ])));
        $html[] = $this->note();

        $takeaways = array_values(array_filter([
            $closing->isNotEmpty() ? 'Last day to apply: '.$this->nameList($closing, 4) : null,
            $listing->isNotEmpty() ? 'Listing today: '.$this->nameList($listing, 4) : null,
            $opening->isNotEmpty() ? 'Opening today: '.$this->nameList($opening, 4) : null,
            $top ? 'Highest GMP: '.$top->name.' at '.$this->gmpText($top) : null,
            $allotment->isNotEmpty() ? 'Allotment expected: '.$this->nameList($allotment, 4) : null,
            $listedLast->isNotEmpty() && $listedLast->first()->listingGainPercent() !== null ? 'Best debut on '.$lastDay->format('j M').': '.$listedLast->first()->name.' at '.$this->percent($listedLast->first()->listingGainPercent()) : null,
        ]));

        return [
            'slug' => $slug,
            'category' => 'daily',
            'title' => $title,
            'seo_title' => 'IPO Today ('.$label.'): GMP, Closing & Listing IPOs',
            'excerpt' => Str::limit(Str::ucfirst($summary !== '' ? $summary : 'GMP and dates for the IPOs open today').'. Price bands, GMP, financials and key dates for '.$day->format('j F Y').'.', 315),
            'seo_description' => Str::limit('IPO today, '.$label.': '.$this->nameList($closing->merge($listing)->merge($opening)->merge($stillOpen)->unique('id'), 3).'. GMP, price bands, lot size, financials, allotment and listing dates.', 155),
            'takeaways' => implode("\n", array_slice($takeaways, 0, 4)),
            'body' => implode("\n", array_filter($html)),
            'image' => $cover ? ['path' => $cover['path'], 'alt' => 'IPO today '.$label.': '.$title] : null,
            'ipo_ids' => $closing->merge($opening)->merge($listing)->merge($allotment)->merge($stillOpen)->pluck('id')->unique()->values()->all(),
        ];
    }

    /**
     * The week-ahead calendar for the week starting on $monday.
     *
     * @return Draft|null
     */
    public function weekly(Carbon $monday): ?array
    {
        $start = $monday->copy()->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();
        $in = fn (?Carbon $d): bool => $d !== null && $d->betweenIncluded($start, $end);
        $all = $this->ipos();

        $bidding = $all->filter(fn (Ipo $ipo): bool => $ipo->open_date && $ipo->close_date && $ipo->open_date->lte($end) && $ipo->close_date->gte($start))
            ->sortBy(fn (Ipo $ipo): string => $ipo->close_date->toDateString().'|'.str_pad((string) (100000 - (int) $ipo->issue_size), 6, '0', STR_PAD_LEFT))->values();
        $listing = $all->filter(fn (Ipo $ipo): bool => $in($ipo->listing_date))->sortBy(fn (Ipo $ipo): string => $ipo->listing_date->toDateString().$ipo->name)->values();

        if ($bidding->isEmpty() && $listing->isEmpty()) {
            return null;
        }

        $main = $bidding->filter->isMainboard()->values();
        $sme = $bidding->reject->isMainboard()->values();
        $openingThisWeek = $bidding->filter(fn (Ipo $ipo): bool => $in($ipo->open_date));
        $range = $start->format('j M').' – '.$end->format('j M Y');
        $slug = $this->weeklySlug($start);
        $board = $this->gmpLeaders($bidding->merge($all->filter(fn (Ipo $ipo): bool => $ipo->open_date?->gt($end) && $ipo->open_date->lte($end->copy()->addDays(7)))));
        $top = $board->first();

        $title = 'IPOs This Week ('.$range.'): '.$bidding->count().' '.$this->ipoWord($bidding->count()).' Open'
            .($listing->isNotEmpty() ? ', '.$listing->count().' '.Str::plural('Listing', $listing->count()) : '');

        $days = [];
        for ($d = $start->copy(); $d->lt($start->copy()->addDays(5)); $d->addDay()) {
            $days[] = [
                'day' => $d->format('l'), 'date' => $d->format('j M'),
                'opening' => $bidding->filter(fn (Ipo $ipo): bool => $ipo->open_date->isSameDay($d))->count(),
                'closing' => $bidding->filter(fn (Ipo $ipo): bool => $ipo->close_date->isSameDay($d))->count(),
                'listing' => $listing->filter(fn (Ipo $ipo): bool => $ipo->listing_date->isSameDay($d))->count(),
            ];
        }

        $cover = $this->images->cover($slug.'-cover', 'Weekly IPO wrap', 'IPOs This Week: '.$range, 'Every IPO open for bidding and every listing, Monday to Friday', array_values(array_filter([
            [(string) $bidding->count(), 'IPOs taking bids'],
            [$main->count().' + '.$sme->count(), 'Mainboard + SME'],
            [(string) $listing->count(), 'Listings this week'],
            $top ? [$this->percent($top->gmpPercent()), 'Top GMP: '.$this->shortName($top)] : null,
        ])), $main->merge($sme)->take(9));
        $weekChart = $this->images->weekChart($slug.'-week', 'The IPO week at a glance', 'IPOs opening, closing and listing each trading day, mainboard and SME together', $days,
            'Source: offer documents and IPO Darbaar data, '.now()->format('j M Y'));

        $html = [];
        $html[] = '<p>Here is the IPO calendar for the week of '.e($range).'. '.$this->countWord($bidding->count(), 'IPO').' '.($bidding->count() === 1 ? 'takes' : 'take').' bids at some point this week ('
            .$this->countWord($main->count(), 'mainboard issue').' and '.$this->countWord($sme->count(), 'SME issue').'), and '.$this->countWord($listing->count(), 'company', 'companies').' '.($listing->count() === 1 ? 'is' : 'are').' due to list.'
            .($openingThisWeek->isNotEmpty() ? ' '.$this->countWord($openingThisWeek->count(), 'new issue').' '.($openingThisWeek->count() === 1 ? 'opens' : 'open').' during the week.' : '').'</p>';
        // The cover is the post's featured image, shown above the text.
        $html[] = '<p>Below are the dates, price bands and issue sizes for every issue, short profiles of the mainboard and new SME IPOs with their financials, the listing schedule and the expected allotment dates. Grey market premium (GMP) figures are as of '.now()->format('j M, g:i A').' and change daily.</p>';
        $html[] = '<h2>The week at a glance</h2>';
        $html[] = $this->figure($weekChart, 'IPOs opening, closing and listing each day, '.$range, 'Number of IPOs opening, closing and listing each trading day of the week.');

        if ($main->isNotEmpty()) {
            $html[] = '<h2>Mainboard IPOs this week</h2>';
            $html[] = $this->table(['IPO', 'Opens', 'Closes', 'Price band', 'Issue size', 'Listing'], $main->map(fn (Ipo $ipo): array => [
                $this->link($ipo), $this->date($ipo->open_date), $this->date($ipo->close_date), $ipo->priceBand(), $this->size($ipo), $this->date($ipo->listing_date),
            ])->all());
            foreach ($main as $ipo) {
                $html[] = $this->profile($ipo);
            }
        }

        if ($sme->isNotEmpty()) {
            $html[] = '<h2>SME IPOs this week</h2>';
            $html[] = '<p>SME IPOs list on the BSE SME and NSE Emerge platforms. Their lot sizes are large, so a single application costs much more than on the mainboard, and trading after listing can be thin. Read <a href="/ipo-guide/sme-ipo-vs-mainboard-ipo">SME IPO vs mainboard IPO</a> before applying.</p>';
            $html[] = $this->table(['IPO', 'Price band', 'Lot value', 'Issue size', 'Closes', 'Listing'], $sme->map(fn (Ipo $ipo): array => [
                $this->link($ipo), $ipo->priceBand(), $this->lotValue($ipo), $this->size($ipo), $this->date($ipo->close_date), $this->date($ipo->listing_date),
            ])->all());
            // New SME issues get a profile too; older ones are covered by the table.
            foreach ($sme->filter(fn (Ipo $ipo): bool => $in($ipo->open_date))->sortByDesc('issue_size')->take(4) as $ipo) {
                $html[] = $this->profile($ipo);
            }
        }

        if ($listing->isNotEmpty()) {
            $html[] = '<h2>IPO listings this week</h2>';
            $html[] = $this->table(['Listing date', 'IPO', 'Board', 'Issue price', 'GMP', 'Expected listing'], $listing->map(fn (Ipo $ipo): array => [
                $ipo->listing_date->format('D, j M'), $this->link($ipo), $ipo->typeLabel(), $ipo->price ? Ipo::money($ipo->price) : '—', $this->gmpText($ipo),
                $ipo->estListingPrice() !== null ? Ipo::money($ipo->estListingPrice()) : '—',
            ])->all());
            $largest = $listing->filter(fn (Ipo $ipo): bool => $this->plausibleSize($ipo))->sortByDesc('issue_size')->take(3);
            if ($largest->isNotEmpty()) {
                $html[] = '<p>The largest listings by issue size are '.$this->joinClauses($largest->map(fn (Ipo $ipo): string => e($ipo->name).' ('.$this->size($ipo).', '.$ipo->listing_date->format('j M').')')->all()).'. The <a href="/ipo-listing-today">IPO listing today</a> page shows the actual listing price as soon as trading starts.</p>';
            }
        }

        $closingThisWeek = $bidding->filter(fn (Ipo $ipo): bool => $in($ipo->close_date))->sortBy('close_date');
        if ($closingThisWeek->isNotEmpty()) {
            $html[] = '<h2>Allotment and listing timetable</h2>';
            $html[] = '<p>Under the T+3 timetable, the basis of allotment is finalised one working day after an issue closes and listing follows on the third working day. Allotment dates below are estimates; exchange holidays push them back by a day.</p>';
            $html[] = $this->table(['IPO', 'Closes', 'Allotment (expected)', 'Listing'], $closingThisWeek->map(fn (Ipo $ipo): array => [
                $this->link($ipo), $this->date($ipo->close_date), $this->date($this->digest->allotmentDate($ipo)), $this->date($ipo->listing_date),
            ])->values()->all());
            $html[] = '<p>Check your result on the <a href="/ipo-allotment-status">IPO allotment status</a> page once the registrar publishes it.</p>';
        }

        if ($board->isNotEmpty()) {
            $html[] = '<h2>GMP this week</h2>';
            $html[] = '<p>'.e($top->name).' leads the grey market at '.e($this->gmpText($top)).'. The chart ranks every open and upcoming issue with a GMP quote by its premium over the upper price band.</p>';
            $html[] = $this->figure($this->images->gmpChart($slug.'-gmp', 'Grey market premium this week', 'GMP as a share of the upper price band, highest first', $board,
                'GMP is unofficial · as of '.now()->format('g:i A, j M Y')), 'IPO GMP this week, '.$range, 'Grey market premium of this week\'s IPOs. Live figures: IPO GMP page.');
        }

        $html[] = '<h2>How to use this week\'s list</h2>';
        $html[] = '<ul><li><strong>Read beyond the GMP.</strong> Look at what the company does, its revenue and profit trend, and what it will do with the money. The profiles above summarise these for each mainboard and new SME issue.</li>'
            .'<li><strong>Watch the last day.</strong> Institutional (QIB) bids usually arrive on the final day, so subscription figures can change a lot in the last few hours.</li>'
            .'<li><strong>Know what you are paying.</strong> The <a href="/calculators/ipo-application">IPO application calculator</a> shows the amount blocked for one lot or more, and the <a href="/calculators/ipo-allotment-chance">allotment chance calculator</a> estimates your odds.</li>'
            .'<li><strong>Keep track of dates.</strong> The <a href="/ipo-calendar">IPO calendar</a> lists every date, and you can add any IPO to your phone\'s calendar from its page.</li></ul>';

        $html[] = $this->faqs(array_values(array_filter([
            ['How many IPOs open this week?', $bidding->count().' '.$this->ipoWord($bidding->count()).' take bids between '.$start->format('j F').' and '.$end->format('j F Y').': '.$main->count().' on the mainboard and '.$sme->count().' on the SME platforms.'],
            $listing->isNotEmpty() ? ['Which IPOs list this week?', $this->nameList($listing, 8).'.'] : null,
            $top ? ['Which IPO has the highest GMP this week?', $top->name.', at '.$this->gmpText($top).' as of '.now()->format('j M').'. GMP is unofficial and changes daily.'] : null,
            ['Can I apply for more than one IPO in the same week?', 'Yes. Each application blocks money in your bank account until the basis of allotment, so you need enough balance for every application you place. Money for IPOs you are not allotted is released after allotment.'],
        ])));
        $html[] = $this->note();

        return [
            'slug' => $slug,
            'category' => 'weekly-wrap',
            'title' => $title,
            'seo_title' => Str::limit('IPOs This Week ('.$range.'): Dates & GMP', 60, ''),
            'excerpt' => Str::limit($bidding->count().' '.$this->ipoWord($bidding->count()).' take bids this week ('.$main->count().' mainboard, '.$sme->count().' SME) and '.$listing->count().' '.Str::plural('company', $listing->count()).' list. Dates, price bands, GMP, financials and the allotment timetable.', 315),
            'seo_description' => Str::limit('IPOs this week ('.$range.'): '.$this->nameList($main->isNotEmpty() ? $main : $sme, 3).'. Dates, price bands, GMP and listing schedule.', 155),
            'takeaways' => implode("\n", array_values(array_filter([
                $bidding->count().' '.$this->ipoWord($bidding->count()).' take bids: '.$main->count().' mainboard and '.$sme->count().' SME',
                $listing->isNotEmpty() ? $listing->count().' '.Str::plural('company', $listing->count()).' list between '.$listing->first()->listing_date->format('j M').' and '.$listing->last()->listing_date->format('j M') : null,
                $main->isNotEmpty() ? 'Mainboard issues: '.$this->nameList($main, 4) : null,
                $top ? 'Highest GMP: '.$top->name.' at '.$this->gmpText($top) : null,
            ]))),
            'body' => implode("\n", array_filter($html)),
            'image' => $cover ? ['path' => $cover['path'], 'alt' => 'IPOs this week, '.$range] : null,
            'ipo_ids' => $bidding->merge($listing)->pluck('id')->unique()->values()->all(),
        ];
    }

    /**
     * The evening recap of the day's listings: listing price against issue price and GMP,
     * day-1 close, subscription, and how close the grey market came.
     *
     * @return Draft|null
     */
    public function listingRecap(Carbon $day): ?array
    {
        $day = $day->copy()->startOfDay();
        if ($day->isWeekend()) {
            return null;
        }

        $listed = Ipo::query()
            ->whereDate('listing_date', $day->toDateString())->whereNotNull('listing_price')->where('price', '>', 0)
            ->with('detail')->get()
            ->filter(fn (Ipo $ipo): bool => $ipo->listing_price >= $ipo->price * 0.3 && $ipo->listing_price <= $ipo->price * 3)
            ->sortByDesc(fn (Ipo $ipo): float => $ipo->listingGainPercent())->values();
        if ($listed->isEmpty()) {
            return null;
        }

        $label = $day->format('j M Y');
        $long = $day->format('l, j F Y');
        $slug = $this->listingSlug($day);
        $best = $listed->first();
        $worst = $listed->last();
        $up = $listed->filter(fn (Ipo $ipo): bool => $ipo->listingGainPercent() > 0);
        $down = $listed->filter(fn (Ipo $ipo): bool => $ipo->listingGainPercent() < 0);
        $withGmp = $listed->filter(fn (Ipo $ipo): bool => $ipo->gmpErrorPoints() !== null);
        $close10 = $withGmp->filter(fn (Ipo $ipo): bool => abs($ipo->gmpErrorPoints()) <= 10);
        $average = round($listed->avg(fn (Ipo $ipo): float => $ipo->listingGainPercent()), 2);
        $main = $listed->filter->isMainboard()->values();
        $sme = $listed->reject->isMainboard()->values();
        $verb = fn (float $pct): string => abs(round($pct)).'% '.($pct >= 0 ? 'Higher' : 'Lower');

        $title = 'IPO Listing Recap ('.$label.'): '.$best->name.' Lists '.$verb($best->listingGainPercent())
            .($listed->count() > 1 ? ', '.$listed->count().' IPOs Debut' : '');

        $cover = $this->images->cover($slug.'-cover', 'Listing day recap', $title, $long.' · listing prices vs issue price and GMP', array_values(array_filter([
            [(string) $listed->count(), $this->ipoWord($listed->count()).' listed'],
            [$this->percent($best->listingGainPercent()), 'Best: '.$this->shortName($best)],
            $listed->count() > 1 ? [$this->percent($average), 'Average listing gain'] : null,
            $withGmp->isNotEmpty() ? [$close10->count().' of '.$withGmp->count(), 'Within 10 pts of GMP'] : null,
        ])), $listed);

        $html = [];
        $html[] = '<p>'.e(Str::ucfirst($this->countWord($listed->count(), 'company', 'companies'))).' made '.($listed->count() === 1 ? 'its' : 'their').' stock market debut on '.e($long)
            .($main->isNotEmpty() && $sme->isNotEmpty() ? ' ('.$main->count().' on the mainboard and '.$sme->count().' on the SME platforms)' : '').'. '
            .e($up->count().' opened above the issue price and '.$down->count().' below.').' '
            .e($best->name.' had the strongest start, opening '.$this->percent($best->listingGainPercent()).' at '.Ipo::money($best->listing_price).' against an issue price of '.Ipo::money($best->price))
            .($listed->count() > 1 && $worst->isNot($best) ? e('; '.$worst->name.' was the weakest at '.$this->percent($worst->listingGainPercent())) : '').'.</p>';
        $html[] = '<p>Below: each listing price against the issue price and the last grey market premium (GMP), where the shares closed on day one, and how the issues were subscribed. Listing prices are the first trades on NSE, or BSE for issues listed only there.</p>';

        $html[] = '<h2>Listing day at a glance</h2>';
        $html[] = $this->table(['IPO', 'Board', 'Issue price', 'GMP estimate', 'Listing price', 'Listing gain', 'Day-1 close', 'Close vs issue'], $listed->map(fn (Ipo $ipo): array => [
            $this->link($ipo), $ipo->typeLabel(), Ipo::money($ipo->price),
            $ipo->gmpEstimatePercent() !== null ? Ipo::money($ipo->price + $ipo->listing_gmp).' ('.$this->percent($ipo->gmpEstimatePercent()).')' : '—',
            Ipo::money($ipo->listing_price), $this->percent($ipo->listingGainPercent()),
            $ipo->listing_close ? Ipo::money($ipo->listing_close) : '—', $ipo->listingCloseGainPercent() !== null ? $this->percent($ipo->listingCloseGainPercent()) : '—',
        ])->all());

        if ($withGmp->isNotEmpty()) {
            $miss = $withGmp->sortByDesc(fn (Ipo $ipo): float => abs($ipo->gmpErrorPoints()))->first();
            $html[] = '<h2>GMP vs the actual listing</h2>';
            $html[] = '<p>The grey market came within 10 percentage points of the listing gain for '.e($close10->count().' of '.$withGmp->count()).' '.e($this->ipoWord($withGmp->count()))
                .' and within 5 points for '.e((string) $withGmp->filter(fn (Ipo $ipo): bool => abs($ipo->gmpErrorPoints()) <= 5)->count()).'. '
                .e('The biggest miss was '.$miss->name.', which opened '.number_format(abs($miss->gmpErrorPoints()), 1).' points '.($miss->gmpErrorPoints() >= 0 ? 'above' : 'below').' what its GMP pointed to.')
                .' Our <a href="/ipo-gmp-accuracy">GMP accuracy tracker</a> keeps the running record across every listing.</p>';
            $html[] = $this->figure($this->images->gmpVsActualChart($slug.'-gmp-vs-actual', 'GMP vs actual listing gain', $long.': the gain the last GMP pointed to and where each IPO opened', $listed,
                'Listing prices from NSE and BSE · GMP is unofficial'), 'GMP vs actual listing gain, '.$label, 'Gold: the listing gain the last GMP before listing pointed to. Green or red: the actual listing gain.');
        }

        if ($main->isNotEmpty()) {
            $html[] = '<h2>Mainboard listings</h2>';
            foreach ($main as $ipo) {
                $html[] = $this->listingProfile($ipo);
            }
        }

        if ($sme->isNotEmpty()) {
            $html[] = '<h2>SME listings</h2>';
            $html[] = $this->table(['IPO', 'Issue price', 'Listing price', 'Listing gain', 'Day-1 close', 'Subscribed'], $sme->map(fn (Ipo $ipo): array => [
                $this->link($ipo), Ipo::money($ipo->price), Ipo::money($ipo->listing_price), $this->percent($ipo->listingGainPercent()),
                $ipo->listing_close ? Ipo::money($ipo->listing_close).' ('.$this->percent($ipo->listingCloseGainPercent()).')' : '—', $this->times($ipo->subscription_total),
            ])->all());
            // Short stories for the most notable SME debuts: the best and, when different, the weakest.
            foreach ($sme->count() > 1 ? collect([$sme->first(), $sme->last()]) : $sme as $notable) {
                $html[] = $this->listingProfile($notable);
            }
            $capped = $sme->filter(fn (Ipo $ipo): bool => $ipo->listingGainPercent() >= 89.5);
            if ($capped->isNotEmpty()) {
                $html[] = '<p>'.e($this->nameList($capped)).' opened at the 90% limit that SME shares are allowed on their first day, so their true demand may have been even higher than the listing price shows.</p>';
            }
        }

        $next = Ipo::query()->whereDate('listing_date', '>', $day->toDateString())->whereDate('listing_date', '<=', $day->copy()->addDays(7)->toDateString())
            ->whereNull('listing_price')->orderBy('listing_date')->get();
        if ($next->isNotEmpty()) {
            $html[] = '<h2>Next listings</h2>';
            $html[] = '<p>These IPOs are due to list in the coming days. The GMP estimate is where the grey market expects them to open; as today showed, the actual listing can differ.</p>';
            $html[] = $this->table(['IPO', 'Board', 'Listing date', 'Issue price', 'GMP', 'GMP estimate'], $next->map(fn (Ipo $ipo): array => [
                $this->link($ipo), $ipo->typeLabel(), $this->date($ipo->listing_date), $ipo->price ? Ipo::money($ipo->price) : '—', $this->gmpText($ipo),
                $ipo->estListingPrice() !== null ? Ipo::money($ipo->estListingPrice()) : '—',
            ])->all());
        }

        $html[] = '<h2>What the day tells us</h2>';
        $points = array_filter([
            $listed->count() > 1 ? 'The average listing gain was '.$this->percent($average).', and '.$up->count().' of '.$listed->count().' listings opened at a premium.' : null,
            $this->subscriptionPattern($listed),
            $this->dayOnePattern($listed),
        ]);
        $html[] = '<ul>'.implode('', array_map(fn (string $point): string => '<li>'.e($point).'</li>', $points)).'<li>Listing prices for every IPO are on the <a href="/ipo-listing-today">IPO listing today</a> page, and year-wise results on the <a href="/ipo-report-card">IPO report card</a>.</li></ul>';

        $html[] = $this->faqs(array_values(array_filter([
            ['Which IPOs listed on '.$label.'?', $this->nameList($listed, 10).'.'],
            ['Which IPO gave the best listing gain on '.$label.'?', $best->name.' opened '.$this->percent($best->listingGainPercent()).' at '.Ipo::money($best->listing_price).' against an issue price of '.Ipo::money($best->price)
                .($best->listing_close ? ' and closed the day at '.Ipo::money($best->listing_close).' ('.$this->percent($best->listingCloseGainPercent()).').' : '.')],
            $withGmp->isNotEmpty() ? ['How accurate was GMP on '.$label.'?', 'GMP was within 10 percentage points of the listing gain for '.$close10->count().' of '.$withGmp->count().' IPOs. GMP is unofficial and can differ sharply from the listing price.'] : null,
        ])));
        $html[] = $this->note();

        return [
            'slug' => $slug,
            'category' => 'listing-recap',
            'title' => $title,
            'seo_title' => 'IPO Listing Recap ('.$label.'): Listing Gains vs GMP',
            'excerpt' => Str::limit($listed->count().' '.$this->ipoWord($listed->count()).' listed on '.$label.': '.$up->count().' at a premium and '.$down->count().' at a discount. '
                .$best->name.' opened '.$this->percent($best->listingGainPercent()).'. Listing prices, day-1 closes, GMP and subscription.', 315),
            'seo_description' => Str::limit('IPO listing recap '.$label.': '.$this->nameList($listed, 3).'. Listing price vs issue price and GMP, day-1 close and subscription.', 155),
            'takeaways' => implode("\n", array_values(array_filter([
                'Best listing: '.$best->name.' at '.$this->percent($best->listingGainPercent()),
                $listed->count() > 1 ? 'Weakest: '.$worst->name.' at '.$this->percent($worst->listingGainPercent()) : null,
                $listed->count() > 1 ? 'Average listing gain: '.$this->percent($average).' across '.$listed->count().' IPOs' : null,
                $withGmp->isNotEmpty() ? 'GMP was within 10 points for '.$close10->count().' of '.$withGmp->count() : null,
            ]))),
            'body' => implode("\n", array_filter($html)),
            'image' => $cover ? ['path' => $cover['path'], 'alt' => 'IPO listing recap '.$label] : null,
            'ipo_ids' => $listed->pluck('id')->all(),
        ];
    }

    public function listingSlug(Carbon $day): string
    {
        return 'ipo-listing-recap-'.Str::lower($day->format('j-F-Y'));
    }

    public function dailySlug(Carbon $day): string
    {
        return 'ipo-today-'.Str::lower($day->format('j-F-Y'));
    }

    public function weeklySlug(Carbon $monday): string
    {
        return 'ipos-this-week-'.Str::lower($monday->format('j-M').'-'.$monday->copy()->addDays(6)->format('j-M-Y'));
    }

    /**
     * A short profile of one IPO: business, issue structure, price, GMP, financials and use of money.
     */
    private function profile(Ipo $ipo): string
    {
        $detail = $ipo->detail;
        $out = ['<h3>'.e($ipo->name).' IPO</h3>'];

        $business = IpoInsights::business($ipo);
        $structure = IpoInsights::structure($ipo);
        $promoters = IpoInsights::promoters($ipo);
        $out[] = '<p>'.e(trim(implode(' ', array_filter([$business, $structure, $promoters])))).'</p>';

        $price = str_contains($ipo->priceBand(), '–') ? 'The price band is '.$ipo->priceBand().' a share' : ($ipo->price ? 'The issue price is '.$ipo->priceBand().' a share' : 'The price band has not been announced yet');
        $lot = $ipo->lot_size && $ipo->price ? '; one lot of '.number_format($ipo->lot_size).' shares costs '.Ipo::money($ipo->lotAmount()).' at the upper price' : '';
        $allot = $this->digest->allotmentDate($ipo);
        $dates = $ipo->open_date && $ipo->close_date
            ? ' Bidding runs from '.$ipo->open_date->format('D, j M').' to '.$ipo->close_date->format('D, j M').($allot ? ', the basis of allotment is expected on '.$allot->format('D, j M') : '')
                .($ipo->listing_date ? ' and the shares are due to list on '.$ipo->listing_date->format('D, j M').' on '.$ipo->exchangeLabel() : '').'.'
            : '';
        $out[] = '<p>'.e($price.$lot.'.'.$dates).'</p>';
        $out[] = '<p>'.$this->gmpSentence($ipo).'</p>';
        if ($subscribed = $this->subscriptionSentence($ipo)) {
            $out[] = '<p>'.e($subscribed).'</p>';
        }

        $out[] = $this->financials($ipo);

        $objects = collect(preg_split('/\R/', (string) $detail?->objects) ?: [])
            ->map(fn (string $line): string => trim(preg_replace('/\.?\s*~\s*Rs\.?\s*([\d,.]+?)\.?\s*Cr\.?/i', ': about ₹$1 crore', ltrim($line, " \t-•*")) ?? ''))
            ->filter(fn (string $line): bool => mb_strlen($line) > 3)
            ->take(6);
        if ($objects->isNotEmpty()) {
            $out[] = '<p>The company plans to use the fresh issue money for:</p><ul>'.$objects->map(fn (string $line): string => '<li>'.e(rtrim($line, '.')).'</li>')->implode('').'</ul>';
        }

        $ratios = $detail ? array_filter([
            $detail->roe !== null ? 'return on equity (RoE) of '.$this->num($detail->roe).'%' : null,
            $detail->roce !== null ? 'RoCE of '.$this->num($detail->roce).'%' : null,
            $detail->debt_equity !== null ? 'a debt-to-equity ratio of '.$this->num($detail->debt_equity) : null,
        ]) : [];
        $facts = array_filter([
            $ratios !== [] ? 'The offer document reports '.$this->joinClauses(array_values($ratios)).'.' : null,
            $detail?->lead_managers ? 'Lead manager: '.rtrim(Str::limit(str_replace("\n", ', ', $detail->lead_managers), 140), '. ').'.' : null,
            $ipo->registrar ? 'Registrar: '.$ipo->registrar.'.' : null,
        ]);
        $out[] = '<p>'.e(implode(' ', $facts)).($facts !== [] ? ' ' : '').'<a href="'.e($this->path($ipo)).'">'.e($ipo->name).' IPO: live GMP, subscription and allotment</a></p>';

        return implode("\n", array_filter($out));
    }

    /** One mainboard listing told as a short story: open, GMP, close, money per lot, subscription. */
    private function listingProfile(Ipo $ipo): string
    {
        $gain = $ipo->listingGainPercent();
        $sentences = [
            IpoInsights::business($ipo),
            $this->plausibleSize($ipo) ? 'It raised '.$this->size($ipo).' in its IPO.' : null,
            $ipo->name.' opened at '.Ipo::money($ipo->listing_price).($ipo->listing_exchange ? ' on '.$ipo->listing_exchange : '').', '.$this->percent($gain).' against the issue price of '.Ipo::money($ipo->price).'.',
            $ipo->lot_size && abs($ipo->listing_price - $ipo->price) >= 0.01 ? 'For an investor allotted one lot of '.number_format($ipo->lot_size).' shares, that is '.($gain >= 0 ? 'a gain' : 'a loss').' of about '.Ipo::money(abs($ipo->listing_price - $ipo->price) * $ipo->lot_size).' at the opening price.' : null,
            $ipo->gmpErrorPoints() !== null ? 'The last GMP before listing was '.($ipo->listing_gmp < 0 ? '-₹' : '₹').Ipo::num(abs($ipo->listing_gmp)).', pointing to about '.Ipo::money($ipo->price + $ipo->listing_gmp)
                .', so the listing came in '.number_format(abs($ipo->gmpErrorPoints()), 1).' points '.($ipo->gmpErrorPoints() >= 0 ? 'above' : 'below').' the grey market estimate.' : null,
            $ipo->listing_close ? 'The shares closed the day at '.Ipo::money($ipo->listing_close).' ('.$this->percent($ipo->listingCloseGainPercent()).' against the issue price), '
                .($ipo->listing_close > $ipo->listing_price ? 'above' : ($ipo->listing_close < $ipo->listing_price ? 'below' : 'level with')).' where they opened.' : null,
            $ipo->subscription_total !== null ? 'The issue had been subscribed '.$this->times($ipo->subscription_total).' overall'
                .($ipo->subscription_qib !== null ? ' (QIB '.$this->times($ipo->subscription_qib).', NII '.$this->times($ipo->subscription_nii).', retail '.$this->times($ipo->subscription_retail).')' : '').'.' : null,
        ];

        return '<h3>'.e($ipo->name).'</h3><p>'.e(implode(' ', array_filter($sentences))).' <a href="'.e($this->path($ipo)).'">'.e($ipo->name).' IPO page</a></p>';
    }

    /**
     * @param  Collection<int, Ipo>  $listed
     */
    private function subscriptionPattern(Collection $listed): ?string
    {
        $known = $listed->filter(fn (Ipo $ipo): bool => $ipo->subscription_total !== null);
        $hot = $known->filter(fn (Ipo $ipo): bool => $ipo->subscription_total >= 50);
        $rest = $known->filter(fn (Ipo $ipo): bool => $ipo->subscription_total < 50);
        if ($hot->isEmpty() || $rest->isEmpty()) {
            return null;
        }

        return 'Issues subscribed 50 times or more opened '.$this->percent($hot->avg(fn (Ipo $ipo): float => $ipo->listingGainPercent())).' on average, against '
            .$this->percent($rest->avg(fn (Ipo $ipo): float => $ipo->listingGainPercent())).' for the rest.';
    }

    /**
     * @param  Collection<int, Ipo>  $listed
     */
    private function dayOnePattern(Collection $listed): ?string
    {
        $closed = $listed->filter(fn (Ipo $ipo): bool => $ipo->listing_close !== null);
        if ($closed->isEmpty()) {
            return null;
        }
        $higher = $closed->filter(fn (Ipo $ipo): bool => $ipo->listing_close > $ipo->listing_price)->count();
        $lower = $closed->filter(fn (Ipo $ipo): bool => $ipo->listing_close < $ipo->listing_price)->count();

        return 'By the close, '.$higher.' of '.$closed->count().' had risen above their listing price and '.$lower.' had fallen below it.';
    }

    private function gmpSentence(Ipo $ipo): string
    {
        $link = '<a href="/ipo-gmp">live IPO GMP</a>';
        if (! $ipo->hasGmp() || ! $ipo->price) {
            return 'There is no reliable grey market quote for this IPO yet; follow the '.$link.' table for updates.';
        }
        if ($ipo->gmp > 0) {
            return 'In the grey market, the shares last traded at a premium (GMP) of '.e(Ipo::money($ipo->gmp)).', '.e(number_format($ipo->gmpPercent(), 1)).'% above the upper price, which points to a listing around '.e(Ipo::money($ipo->estListingPrice())).'. GMP is unofficial and can change quickly; see the '.$link.' table.';
        }
        if ($ipo->gmp < 0) {
            return 'The grey market premium is negative at -'.e(Ipo::money(abs($ipo->gmp))).', which suggests a listing below the issue price. GMP is unofficial; see the '.$link.' table.';
        }

        return 'The GMP is flat at ₹0, which suggests a listing close to the issue price. GMP is unofficial; see the '.$link.' table.';
    }

    private function subscriptionSentence(Ipo $ipo): ?string
    {
        if ($ipo->subscription_total === null) {
            return null;
        }
        $parts = array_filter([
            $ipo->subscription_qib !== null ? 'QIB '.$this->times($ipo->subscription_qib) : null,
            $ipo->subscription_nii !== null ? 'NII '.$this->times($ipo->subscription_nii) : null,
            $ipo->subscription_retail !== null ? 'retail '.$this->times($ipo->subscription_retail) : null,
        ]);

        return ($ipo->subscription_updated_at ? 'By '.$ipo->subscription_updated_at->format('D, j M, g:i A').', the' : 'The')
            .' issue was subscribed '.$this->times($ipo->subscription_total).' overall'.($parts !== [] ? ' ('.$this->joinClauses(array_values($parts)).')' : '').', as per NSE data for bids on both exchanges.';
    }

    private function times(?float $value): string
    {
        return IpoInsights::times($value);
    }

    private function financials(Ipo $ipo): ?string
    {
        /** @var Collection<int, IpoFinancial> $rows */
        $rows = $ipo->financials->sortBy('period_end')->values()->take(-4)->values();
        if ($rows->count() < 2) {
            return null;
        }

        $table = $this->table(['Period', 'Revenue', 'Profit after tax', 'Net worth', 'Borrowings'], $rows->map(fn (IpoFinancial $f): array => [
            e($f->period), $this->crore($f->revenue), $this->crore($f->pat), $this->crore($f->net_worth), $this->crore($f->borrowings),
        ])->all());

        // Growth is measured over full financial years only; a part-year period would distort it.
        $sentences = IpoInsights::growth($ipo);

        return '<p>Restated financials from the offer document (₹ crore):</p>'.$table.($sentences !== [] ? '<p>'.e(implode(' ', $sentences)).'</p>' : '');
    }

    /**
     * Recent IPOs with believable dates, with the relations the profiles use.
     *
     * @return Collection<int, Ipo>
     */
    private function ipos(): Collection
    {
        return Ipo::query()
            ->where(fn ($q) => $q->whereDate('open_date', '>=', now()->subDays(45)->toDateString())->orWhereDate('listing_date', '>=', now()->subDays(45)->toDateString()))
            ->with(['detail', 'financials'])
            ->get()
            ->filter(fn (Ipo $ipo): bool => ! ($ipo->open_date && $ipo->close_date)
                || ($ipo->close_date->gte($ipo->open_date) && $ipo->open_date->diffInDays($ipo->close_date) <= self::MAX_BIDDING_DAYS))
            ->values();
    }

    /**
     * IPOs with a GMP quote, highest premium first.
     *
     * @param  Collection<int, Ipo>  $ipos
     * @return Collection<int, Ipo>
     */
    private function gmpLeaders(Collection $ipos): Collection
    {
        return $ipos->unique('id')->filter(fn (Ipo $ipo): bool => $ipo->gmpPercent() !== null)
            ->sortByDesc(fn (Ipo $ipo): float => $ipo->gmpPercent())->take(10)->values();
    }

    /**
     * @param  Collection<int, Ipo>  $ipos
     */
    private function bestListing(Collection $ipos): ?string
    {
        $best = $ipos->filter(fn (Ipo $ipo): bool => $ipo->gmpPercent() !== null)->sortByDesc(fn (Ipo $ipo): float => $ipo->gmpPercent())->first();

        return $best ? $best->name.' is expected to list at the highest premium, with a GMP of '.$this->gmpText($best) : null;
    }

    private function plausibleSize(Ipo $ipo): bool
    {
        return IpoInsights::plausibleSize($ipo);
    }

    private function size(Ipo $ipo): string
    {
        return IpoInsights::size($ipo);
    }

    private function lotValue(Ipo $ipo): string
    {
        return $ipo->lotAmount() !== null ? Ipo::money($ipo->lotAmount()).' ('.number_format($ipo->lot_size).' sh.)' : '—';
    }

    private function gmpText(Ipo $ipo): string
    {
        if (! $ipo->hasGmp()) {
            return '—';
        }

        return ($ipo->gmp < 0 ? '-₹' : '₹').Ipo::num(abs($ipo->gmp)).($ipo->gmpPercent() !== null ? ' ('.$this->percent($ipo->gmpPercent()).')' : '');
    }

    private function registrar(Ipo $ipo): string
    {
        $info = $ipo->registrarInfo();
        if (! $info) {
            return '—';
        }

        return $info['url'] ? '<a href="'.e($info['url']).'">'.e($info['name']).'</a>' : e($info['name']);
    }

    private function crore(?float $value): string
    {
        return IpoInsights::crore($value);
    }

    private function num(float $value, int $decimals = 2): string
    {
        return IpoInsights::num($value, $decimals);
    }

    private function percent(float $value): string
    {
        return IpoInsights::percent($value);
    }

    private function date(?Carbon $date): string
    {
        return $date ? $date->format('D, j M') : '—';
    }

    private function path(Ipo $ipo): string
    {
        return route('ipos.show', $ipo, false);
    }

    private function link(Ipo $ipo): string
    {
        return '<a href="'.e($this->path($ipo)).'">'.e($ipo->name).'</a>';
    }

    /**
     * @param  Collection<int, Ipo>  $ipos
     */
    private function linkList(Collection $ipos): string
    {
        return $ipos->map(fn (Ipo $ipo): string => $this->link($ipo))->implode(', ');
    }

    /**
     * "A, B and 3 more"
     *
     * @param  Collection<int, Ipo>  $ipos
     */
    private function nameList(Collection $ipos, int $max = 6): string
    {
        $names = $ipos->pluck('name')->values();
        if ($names->count() > $max) {
            return $names->take($max)->implode(', ').' and '.($names->count() - $max).' more';
        }

        return $this->joinClauses($names->all());
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function joinClauses(array $parts): string
    {
        $parts = array_values($parts);

        return count($parts) <= 1 ? (string) ($parts[0] ?? '') : implode(', ', array_slice($parts, 0, -1)).' and '.end($parts);
    }

    /** First word of the company name, for tight spaces such as image tiles. */
    private function shortName(Ipo $ipo): string
    {
        return Str::limit(Str::before($ipo->name.' ', ' '), 12, '');
    }

    private function ipoWord(int $n): string
    {
        return $n === 1 ? 'IPO' : 'IPOs';
    }

    private function countWord(int $n, string $singular, ?string $plural = null): string
    {
        $words = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];

        $plural ??= $singular === 'IPO' ? 'IPOs' : Str::plural($singular);

        return ($words[$n] ?? (string) $n).' '.($n === 1 ? $singular : $plural);
    }

    /**
     * @param  array{path: string, width: int, height: int}|null  $image
     */
    private function figure(?array $image, string $alt, string $caption): ?string
    {
        if (! $image) {
            return null;
        }

        return '<figure><img src="/uploads/'.e($image['path']).'" alt="'.e($alt).'" width="'.$image['width'].'" height="'.$image['height'].'"><figcaption>'.e($caption).'</figcaption></figure>';
    }

    /**
     * @param  list<string>  $head
     * @param  list<list<string>>  $rows  Cells are trusted HTML (links) or plain text built here.
     */
    private function table(array $head, array $rows): string
    {
        return '<table><thead><tr>'.implode('', array_map(fn (string $h): string => '<th>'.e($h).'</th>', $head)).'</tr></thead><tbody>'
            .implode('', array_map(fn (array $row): string => '<tr>'.implode('', array_map(fn (string $cell): string => '<td>'.$cell.'</td>', $row)).'</tr>', $rows))
            .'</tbody></table>';
    }

    /**
     * @param  list<array{0: string, 1: string}>  $faqs
     */
    private function faqs(array $faqs): string
    {
        return '<h2>Frequently asked questions</h2>'.implode('', array_map(fn (array $faq): string => '<h3>'.e($faq[0]).'</h3><p>'.e($faq[1]).'</p>', $faqs));
    }

    private function note(): string
    {
        return '<p><em>This update is compiled automatically from IPO Darbaar\'s IPO database at '.now()->format('g:i A').' IST on '.now()->format('j F Y')
            .'. Dates, prices and GMP come from offer documents and market sources and can change; the linked IPO pages always show the latest figures. This is not investment advice.</em></p>';
    }
}
