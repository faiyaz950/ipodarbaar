<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Services\IpoStatsService;
use Illuminate\View\View;

class IpoYearController extends Controller
{
    public function index(IpoStatsService $stats): View
    {
        return view('ipos.report-card', [
            'years' => array_map($stats->forYear(...), $stats->years()),
        ]);
    }

    public function show(int $year, IpoStatsService $stats): View
    {
        abort_unless(in_array($year, $stats->years(), true), 404);

        return view('ipos.year', [
            'stats' => $stats->forYear($year),
            'years' => $stats->years(),
            'ipos' => Ipo::query()
                ->whereYear('open_date', $year)
                ->orderByDesc('open_date')
                ->orderBy('name')
                ->paginate(25),
        ]);
    }
}
