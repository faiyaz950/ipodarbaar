<?php

namespace App\Providers;

use App\Models\Ipo;
use App\Services\IpoSyncService;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);

        if ($publicPath = config('app.public_path')) {
            $this->app->usePublicPath($publicPath);
        }
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        RateLimiter::for('digest-mail', fn (): Limit => Limit::perHour((int) config('ipodarbar.mail.hourly_limit', 250)));

        // Site-wide GMP ticker + live counts for the header.
        View::composer('layouts.app', function ($view) {
            $data = Cache::remember('layout:ticker', 120, function () {
                // IPOs with a GMP first, then by stage (open → listing soon → upcoming).
                $active = Ipo::active()->get()->sortBy(fn (Ipo $i) => [
                    $i->hasGmp() ? 0 : 1,
                    ['open' => 0, 'closed' => 1, 'upcoming' => 2][$i->status()] ?? 3,
                    $i->open_date?->timestamp ?? PHP_INT_MAX,
                ])->values();

                return [
                    'ticker' => $active->take(24)->map(fn (Ipo $i) => [
                        'name' => $i->name,
                        'url' => $i->url(),
                        'status' => $i->status(),
                        'price' => $i->price,
                        'gmp' => $i->gmp,
                        'pct' => $i->gmpPercent(),
                    ])->all(),
                    'openCount' => $active->filter(fn (Ipo $i) => $i->status() === 'open')->count(),
                ];
            });

            $view->with('tickerItems', $data['ticker'])
                ->with('liveCount', $data['openCount'])
                ->with('lastSynced', IpoSyncService::lastSyncedAt());
        });
    }
}
