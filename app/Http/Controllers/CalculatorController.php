<?php

namespace App\Http\Controllers;

use App\Support\Calculators;

class CalculatorController extends Controller
{
    public function index()
    {
        return view('calculators.index', [
            'groups' => Calculators::grouped(),
        ]);
    }

    public function show(string $slug)
    {
        $calc = Calculators::find($slug);
        abort_if(! $calc, 404);

        $related = collect(Calculators::grouped()[$calc['group']] ?? [])
            ->reject(fn ($c) => $c['slug'] === $slug)
            ->take(5)
            ->values();

        return view('calculators.show', compact('calc', 'related'));
    }
}
