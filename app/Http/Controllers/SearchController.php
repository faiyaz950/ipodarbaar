<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Services\NewsService;
use App\Support\Calculators;
use App\Support\Guides;
use App\Support\IpoHubs;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Site-wide search across IPOs, IPO lists and tools, guides, calculators and recent news.
 */
class SearchController extends Controller
{
    /** Recent news headlines searched (the news API has no search of its own). */
    private const NEWS_POOL = 100;

    public function index(Request $request, NewsService $news): View
    {
        $q = Str::limit(trim((string) $request->query('q', '')), 80, '');
        $words = collect(preg_split('/\s+/', Str::lower($q)) ?: [])->filter(fn (string $w): bool => mb_strlen($w) >= 2)->values();

        $results = ['ipos' => collect(), 'pages' => collect(), 'guides' => collect(), 'calculators' => collect(), 'news' => collect()];
        if ($words->isNotEmpty()) {
            $results = [
                'ipos' => Ipo::search($q)->orderByRaw('open_date is null')->orderByDesc('open_date')->limit(12)->get(),
                'pages' => $this->match($this->pages(), $words, fn (array $page): string => $page['title'].' '.$page['text'])->take(6),
                'guides' => $this->match(collect(Guides::all())->map(fn (array $g, string $slug): array => $g + ['slug' => $slug])->values(), $words,
                    fn (array $g): string => $g['title'].' '.$g['summary'].' '.$g['description'])->take(6),
                'calculators' => $this->match(collect(Calculators::all())->map(fn (array $c, string $slug): array => $c + ['slug' => $slug])->values(), $words,
                    fn (array $c): string => $c['name'].' '.$c['short'])->take(8),
                'news' => $this->match(collect($news->latest(self::NEWS_POOL)['items']), $words,
                    fn (array $n): string => $n['headline'].' '.$n['category']['name'])->take(8),
            ];
        }

        return view('search.index', [
            'q' => $q,
            'results' => $results,
            'total' => collect($results)->sum(fn (Collection $items): int => $items->count()),
        ]);
    }

    /**
     * Items whose text contains every search word.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<int, string>  $words
     * @return Collection<int, array<string, mixed>>
     */
    private function match(Collection $items, Collection $words, callable $text): Collection
    {
        return $items->filter(function (array $item) use ($words, $text): bool {
            $haystack = Str::lower($text($item));

            return $words->every(fn (string $word): bool => str_contains($haystack, $word));
        })->values();
    }

    /**
     * IPO lists and tools people look for by name.
     *
     * @return Collection<int, array{title: string, text: string, url: string}>
     */
    private function pages(): Collection
    {
        $pages = collect(IpoHubs::all())->map(fn (array $hub, string $key): array => [
            'title' => $hub['label'], 'text' => IpoHubs::fill($hub['h1']).' '.$hub['lead'], 'url' => route('ipos.'.$key),
        ])->values();

        return $pages->concat([
            ['title' => 'IPO GMP Today', 'text' => 'live grey market premium gmp expected listing price', 'url' => route('ipos.gmp')],
            ['title' => 'Mainboard IPO GMP', 'text' => 'mainboard grey market premium gmp', 'url' => route('ipos.gmp.mainboard')],
            ['title' => 'SME IPO GMP', 'text' => 'sme grey market premium gmp', 'url' => route('ipos.gmp.sme')],
            ['title' => 'IPO Listing Today', 'text' => 'listing price listing gain ipos listing this week', 'url' => route('ipos.listing-today')],
            ['title' => 'IPO Calendar', 'text' => 'ipo dates open close allotment listing calendar subscribe', 'url' => route('ipos.calendar')],
            ['title' => 'IPO Report Card', 'text' => 'ipo performance listing gains year funds raised', 'url' => route('ipos.report-card')],
            ['title' => 'SME IPO Dashboard', 'text' => 'sme ipo market dashboard gmp funds raised', 'url' => route('ipos.sme-dashboard')],
            ['title' => 'IPO Portfolio Tracker', 'text' => 'track ipo applications allotment profit portfolio', 'url' => route('portfolio')],
            ['title' => 'Compare IPOs', 'text' => 'compare ipos side by side', 'url' => route('compare')],
            ['title' => 'IPO Alerts', 'text' => 'alerts telegram whatsapp email notifications calendar', 'url' => route('alerts')],
            ['title' => 'My Watchlist', 'text' => 'watchlist starred ipos', 'url' => route('watchlist')],
            ['title' => 'Market News', 'text' => 'share market news ipo news shorts', 'url' => route('news.index')],
            ['title' => 'Share Buybacks', 'text' => 'buyback offers tender offer record date', 'url' => route('actions.buyback')],
            ['title' => 'Rights Issues', 'text' => 'rights issue ratio entitlement', 'url' => route('actions.rights')],
            ['title' => 'NCD Issues', 'text' => 'ncd non convertible debentures bonds coupon', 'url' => route('actions.ncd')],
        ]);
    }
}
