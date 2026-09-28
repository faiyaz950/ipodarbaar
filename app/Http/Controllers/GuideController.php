<?php

namespace App\Http\Controllers;

use App\Support\Guides;
use Illuminate\View\View;

/**
 * IPO Academy: evergreen guides for people learning how IPOs work.
 */
class GuideController extends Controller
{
    public function index(): View
    {
        return view('guides.index', ['guides' => Guides::all()]);
    }

    public function show(string $slug): View
    {
        $guide = Guides::find($slug);
        abort_if(! $guide, 404);

        return view('guides.show', [
            'guide' => $guide,
            'related' => collect($guide['related'])->map(fn (string $related): ?array => Guides::find($related))->filter()->values(),
        ]);
    }
}
