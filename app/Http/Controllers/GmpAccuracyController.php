<?php

namespace App\Http\Controllers;

use App\Services\IpoStatsService;
use Illuminate\View\View;

/**
 * How accurately the grey market premium predicted IPO listing prices.
 */
class GmpAccuracyController extends Controller
{
    public function __invoke(IpoStatsService $stats): View
    {
        return view('ipos.gmp-accuracy', ['accuracy' => $stats->gmpAccuracy()]);
    }
}
