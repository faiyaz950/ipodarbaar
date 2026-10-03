<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ipo;
use App\Models\IpoGmpHistory;
use App\Services\Analytics\AnalyticsReport;
use App\Services\IpoSyncService;
use App\Services\LogoService;
use Illuminate\Http\Request;
use Throwable;

class DashboardController extends Controller
{
    public function index(AnalyticsReport $analytics)
    {
        $active = Ipo::active()->get();
        $today = now()->startOfDay();

        return view('admin.dashboard', [
            'stats' => [
                'total' => Ipo::count(),
                'active' => $active->count(),
                'open' => Ipo::open()->count(),
                'history' => IpoGmpHistory::count(),
            ],
            'traffic' => $analytics->totals($today, $today) + ['live' => $analytics->liveVisitors()],
            'lastSynced' => IpoSyncService::lastSyncedAt(),
            'missingLot' => $active->whereNull('lot_size')->sortBy('open_date')->values(),
            'missingRegistrar' => $active->whereNull('registrar')->sortBy('open_date')->values(),
            'missingListing' => Ipo::listed()->whereNull('listing_price')->whereNotNull('listing_date')
                ->whereDate('listing_date', '>=', now()->subDays(15)->toDateString())
                ->orderByDesc('listing_date')->get(),
        ]);
    }

    public function sync(Request $request, IpoSyncService $sync, LogoService $logos)
    {
        try {
            $result = $request->boolean('full') ? $sync->sync() : $sync->syncRecent();
            $logos->warm(Ipo::active()->get());
        } catch (Throwable $e) {
            return back()->with('error', 'Sync failed: '.$e->getMessage());
        }

        return back()->with('status', "Synced {$result['fetched']} IPOs.");
    }
}
