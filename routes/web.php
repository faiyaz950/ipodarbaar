<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IpoController;
use App\Http\Controllers\IpoVoteController;
use App\Http\Controllers\IpoYearController;
use App\Http\Controllers\LogoController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RelayController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WatchlistController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RefreshIpoData;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// IPOs
Route::get('/ipo', [IpoController::class, 'index'])->name('ipos.index');
Route::get('/ipo/type/{type}', [IpoController::class, 'index'])->whereIn('type', ['mainboard', 'sme'])->name('ipos.type');
Route::get('/ipo-gmp', [IpoController::class, 'gmp'])->name('ipos.gmp');
Route::get('/ipo-calendar', [IpoController::class, 'calendar'])->name('ipos.calendar');
Route::get('/ipo/search/suggest', [IpoController::class, 'suggest'])->name('ipos.suggest');
Route::get('/ipo-compare', [CompareController::class, 'index'])->name('compare');
Route::get('/ipo-report-card', [IpoYearController::class, 'index'])->name('ipos.report-card');
Route::get('/ipo/{year}', [IpoYearController::class, 'show'])->where('year', '20\d\d')->name('ipos.year');
Route::get('/ipo/{ipo}', [IpoController::class, 'show'])->name('ipos.show');
Route::post('/ipo/{ipo}/vote', [IpoVoteController::class, 'store'])->middleware('throttle:10,1')->name('ipos.vote');

// Watchlist (stored in the browser) and offline fallback for the installed app
Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist');
Route::get('/watchlist/items', [WatchlistController::class, 'items'])->name('watchlist.items');
Route::get('/offline', [PageController::class, 'offline'])->name('offline');

// Email digest (double opt-in, signed confirm / unsubscribe links)
Route::post('/subscribe', [SubscriptionController::class, 'store'])->middleware('throttle:5,1')->name('subscribe');
Route::get('/subscribe/{subscriber}/confirm', [SubscriptionController::class, 'confirm'])->middleware('signed')->name('subscribe.confirm');
Route::get('/unsubscribe/{subscriber}', [SubscriptionController::class, 'showUnsubscribe'])->middleware('signed')->name('unsubscribe');
Route::post('/unsubscribe/{subscriber}', [SubscriptionController::class, 'unsubscribe'])->middleware('signed')->name('unsubscribe.store');

// News
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/shorts', [NewsController::class, 'shorts'])->name('news.shorts');
Route::get('/shorts/feed', [NewsController::class, 'feed'])->name('news.feed');
Route::get('/news/{id}/{slug?}', [NewsController::class, 'show'])->whereNumber('id')->name('news.show');

// Calculators
Route::get('/calculators', [CalculatorController::class, 'index'])->name('calculators.index');
Route::get('/calculators/{slug}', [CalculatorController::class, 'show'])->name('calculators.show');

// Pages
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/disclaimer', [PageController::class, 'disclaimer'])->name('disclaimer');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/og/ipo/{file}', OgImageController::class)->where('file', '[a-z0-9-]+\.png')->withoutMiddleware(RefreshIpoData::class)->name('og.ipo');
Route::get('/logos/{file}', LogoController::class)->where('file', '[a-z0-9-]+\.webp')->withoutMiddleware(RefreshIpoData::class)->name('logos');

// Crawlers
Route::withoutMiddleware(RefreshIpoData::class)->group(function () {
    Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
    Route::get('/ads.txt', [SeoController::class, 'adsTxt'])->name('ads-txt');
    Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('sitemap');
    Route::get('/sitemaps/{part}.xml', [SeoController::class, 'sitemap'])->whereIn('part', ['pages', 'ipos', 'news'])->name('sitemaps.show');
});

// IPO relay (see RelayController)
Route::prefix('internal')->name('relay.')->withoutMiddleware(RefreshIpoData::class)->middleware('throttle:300,1')->group(function () {
    Route::post('/ipo-push', [RelayController::class, 'ipos'])->name('ipos');
    Route::get('/logos/missing', [RelayController::class, 'missingLogos'])->name('logos.missing');
    Route::post('/logos/{ipo:api_id}', [RelayController::class, 'logo'])->whereNumber('ipo')->name('logos.store');
});

// Admin
Route::prefix('admin')->name('admin.')->withoutMiddleware(RefreshIpoData::class)->group(function () {
    Route::get('/login', [Admin\AuthController::class, 'show'])->name('login');
    Route::post('/login', [Admin\AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');

    Route::middleware(['auth', EnsureAdmin::class])->group(function () {
        Route::post('/logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::post('/sync', [Admin\DashboardController::class, 'sync'])->middleware('throttle:6,1')->name('sync');
        Route::get('/ipos', [Admin\IpoController::class, 'index'])->name('ipos.index');
        Route::get('/ipos/{ipo}/edit', [Admin\IpoController::class, 'edit'])->name('ipos.edit');
        Route::put('/ipos/{ipo}', [Admin\IpoController::class, 'update'])->name('ipos.update');
        Route::post('/ipos/{ipo}/unlock', [Admin\IpoController::class, 'unlock'])->name('ipos.unlock');
        Route::get('/ipos/{ipo}/details', [Admin\IpoDetailController::class, 'edit'])->name('ipos.details.edit');
        Route::put('/ipos/{ipo}/details', [Admin\IpoDetailController::class, 'update'])->name('ipos.details.update');

        Route::get('/news', [Admin\NewsController::class, 'index'])->name('news.index');
        Route::whereNumber('id')->group(function () {
            Route::get('/news/{id}/edit', [Admin\NewsController::class, 'edit'])->name('news.edit');
            Route::put('/news/{id}', [Admin\NewsController::class, 'update'])->name('news.update');
            Route::post('/news/{id}/visibility', [Admin\NewsController::class, 'toggleHidden'])->name('news.visibility');
            Route::delete('/news/{id}', [Admin\NewsController::class, 'reset'])->name('news.reset');
        });

        Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings');
        Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/ads/toggle', [Admin\SettingsController::class, 'toggleAds'])->name('settings.ads.toggle');

        Route::get('/system', [Admin\SystemController::class, 'index'])->name('system');
        Route::middleware('throttle:6,1')->group(function () {
            Route::post('/system/migrate', [Admin\SystemController::class, 'migrate'])->name('system.migrate');
            Route::post('/system/optimize', [Admin\SystemController::class, 'optimize'])->name('system.optimize');
            Route::post('/system/test-mail', [Admin\SystemController::class, 'testMail'])->name('system.test-mail');
            Route::post('/system/test-telegram', [Admin\SystemController::class, 'testTelegram'])->name('system.test-telegram');
        });
    });
});
