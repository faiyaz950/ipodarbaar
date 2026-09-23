<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Support\Calculators;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function about()
    {
        return view('pages.about');
    }

    public function disclaimer()
    {
        return view('pages.disclaimer');
    }

    public function sitemap(): Response
    {
        $urls = collect([
            route('home'), route('ipos.index'), route('ipos.type', 'mainboard'), route('ipos.type', 'sme'),
            route('ipos.gmp'), route('ipos.calendar'), route('news.index'), route('news.shorts'),
            route('calculators.index'),
        ])->map(fn ($u) => ['loc' => $u, 'lastmod' => now()]);

        foreach (array_keys(Calculators::all()) as $slug) {
            $urls->push(['loc' => route('calculators.show', $slug), 'lastmod' => now()]);
        }

        Ipo::query()->orderByDesc('api_id')->limit(2000)->get(['slug', 'updated_at'])
            ->each(fn ($i) => $urls->push(['loc' => route('ipos.show', $i->slug), 'lastmod' => $i->updated_at]));

        return response()->view('sitemap', ['urls' => $urls], 200)->header('Content-Type', 'application/xml');
    }
}
