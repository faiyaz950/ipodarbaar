<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\CorporateActionController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IpoController;
use App\Http\Controllers\IpoVoteController;
use App\Http\Controllers\IpoYearController;
use App\Http\Controllers\LogoController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\RelayController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WatchlistController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\FlushPageCacheAfterWrites;
use App\Http\Middleware\RefreshIpoData;
use App\Models\CorporateAction;
use App\Support\IpoHubs;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', [SearchController::class, 'index'])->name('search');

// IPOs
Route::get('/ipo', [IpoController::class, 'index'])->name('ipos.index');
foreach (array_keys(IpoHubs::all()) as $hub) {
    Route::get('/'.IpoHubs::find($hub)['path'], [IpoController::class, 'hub'])->defaults('hub', $hub)->name('ipos.'.$hub);
}
Route::get('/ipo/type/{type}', [IpoController::class, 'index'])->whereIn('type', ['mainboard', 'sme'])->name('ipos.type');
Route::get('/ipo-gmp', [IpoController::class, 'gmp'])->name('ipos.gmp');
Route::get('/mainboard-ipo-gmp', [IpoController::class, 'gmp'])->defaults('type', 'mainboard')->name('ipos.gmp.mainboard');
Route::get('/sme-ipo-gmp', [IpoController::class, 'gmp'])->defaults('type', 'sme')->name('ipos.gmp.sme');
Route::get('/ipo-listing-today', [IpoController::class, 'listingToday'])->name('ipos.listing-today');
Route::get('/sme-ipo-dashboard', [IpoController::class, 'smeDashboard'])->name('ipos.sme-dashboard');
Route::get('/ipo-calendar', [IpoController::class, 'calendar'])->name('ipos.calendar');
Route::get('/ipo-calendar.ics', [IpoController::class, 'calendarFeed'])->name('ipos.calendar-feed');
Route::get('/ipo/search/suggest', [IpoController::class, 'suggest'])->name('ipos.suggest');
Route::get('/ipo-compare', [CompareController::class, 'index'])->name('compare');
Route::get('/ipo-report-card', [IpoYearController::class, 'index'])->name('ipos.report-card');
Route::get('/ipo/{year}', [IpoYearController::class, 'show'])->where('year', '20\d\d')->name('ipos.year');
Route::get('/ipo/{ipo}', [IpoController::class, 'show'])->name('ipos.show');
Route::get('/ipo/{ipo}/calendar.ics', [IpoController::class, 'ics'])->name('ipos.ics');
Route::post('/ipo/{ipo}/vote', [IpoVoteController::class, 'store'])->middleware('throttle:10,1')->name('ipos.vote');

// Watchlist (stored in the browser) and offline fallback for the installed app
Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist');
Route::get('/watchlist/items', [WatchlistController::class, 'items'])->name('watchlist.items');
Route::get('/offline', [PageController::class, 'offline'])->name('offline');

// IPO portfolio tracker (stored in the browser; the server only supplies prices)
Route::get('/ipo-portfolio', [PortfolioController::class, 'index'])->name('portfolio');
Route::get('/ipo-portfolio/prices', [PortfolioController::class, 'prices'])->name('portfolio.prices');

// Email digest (double opt-in, signed confirm / unsubscribe links)
Route::post('/subscribe', [SubscriptionController::class, 'store'])->middleware('throttle:5,1')->name('subscribe');
Route::get('/subscribe/{subscriber}/confirm', [SubscriptionController::class, 'confirm'])->middleware('signed')->name('subscribe.confirm');
Route::get('/unsubscribe/{subscriber}', [SubscriptionController::class, 'showUnsubscribe'])->middleware('signed')->name('unsubscribe');
Route::post('/unsubscribe/{subscriber}', [SubscriptionController::class, 'unsubscribe'])->middleware('signed')->name('unsubscribe.store');

// News
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/{categorySlug}-news', [NewsController::class, 'index'])
    ->whereIn('categorySlug', array_column(config('ipodarbar.news_categories'), 'slug'))
    ->name('news.category');
Route::get('/shorts', [NewsController::class, 'shorts'])->name('news.shorts');
Route::get('/shorts/feed', [NewsController::class, 'feed'])->name('news.feed');
Route::get('/news/{id}/{slug?}', [NewsController::class, 'show'])->whereNumber('id')->name('news.show');

// Buybacks, rights issues and NCD issues (entered in the admin panel)
foreach (CorporateAction::TYPES as $actionType => $actionMeta) {
    Route::get('/'.$actionMeta['path'], [CorporateActionController::class, 'index'])->defaults('type', $actionType)->name('actions.'.$actionType);
    Route::get('/'.$actionMeta['path'].'/{offer:slug}', [CorporateActionController::class, 'show'])->defaults('type', $actionType)->name('actions.'.$actionType.'.show');
}

// Blog (original articles by the Research Desk)
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/feed.xml', [BlogController::class, 'feed'])->name('blog.feed');
Route::get('/blog/author/{author:slug}', [BlogController::class, 'author'])->name('blog.author');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('blog.show');

// IPO Academy guides
Route::get('/ipo-guide', [GuideController::class, 'index'])->name('guides.index');
Route::get('/ipo-guide/{slug}', [GuideController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('guides.show');

// Calculators
Route::get('/calculators', [CalculatorController::class, 'index'])->name('calculators.index');
Route::get('/calculators/{slug}', [CalculatorController::class, 'show'])->name('calculators.show');

// Pages
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/editorial-policy', [PageController::class, 'editorialPolicy'])->name('editorial-policy');
Route::get('/ipo-alerts', [PageController::class, 'alerts'])->name('alerts');
Route::get('/disclaimer', [PageController::class, 'disclaimer'])->name('disclaimer');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/og/ipo/{file}', OgImageController::class)->where('file', '[a-z0-9-]+\.png')->withoutMiddleware(RefreshIpoData::class)->name('og.ipo');
Route::get('/logos/{file}', LogoController::class)->where('file', '[a-z0-9-]+\.webp')->withoutMiddleware(RefreshIpoData::class)->name('logos');

// Crawlers
Route::withoutMiddleware(RefreshIpoData::class)->group(function () {
    Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
    Route::get('/ads.txt', [SeoController::class, 'adsTxt'])->name('ads-txt');
    Route::get('/indexnow-key.txt', [SeoController::class, 'indexNowKey'])->name('indexnow.key');
    Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('sitemap');
    Route::get('/sitemaps/{part}.xml', [SeoController::class, 'sitemap'])->whereIn('part', SeoController::SITEMAPS)->name('sitemaps.show');
});

// IPO relay (see RelayController)
Route::prefix('internal')->name('relay.')->withoutMiddleware(RefreshIpoData::class)->middleware('throttle:300,1')->group(function () {
    Route::post('/ipo-push', [RelayController::class, 'ipos'])->name('ipos');
    Route::get('/logos/missing', [RelayController::class, 'missingLogos'])->name('logos.missing');
    Route::post('/logos/{ipo:api_id}', [RelayController::class, 'logo'])->whereNumber('ipo')->name('logos.store');
    Route::get('/pages/stale', [RelayController::class, 'stalePages'])->name('pages.stale');
    Route::post('/pages/{ipo:api_id}', [RelayController::class, 'page'])->whereNumber('ipo')->name('pages.store');
});

// Admin
Route::prefix('admin')->name('admin.')->withoutMiddleware(RefreshIpoData::class)->group(function () {
    Route::get('/login', [Admin\AuthController::class, 'show'])->name('login');
    Route::post('/login', [Admin\AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');

    Route::middleware(['auth', EnsureAdmin::class, FlushPageCacheAfterWrites::class])->group(function () {
        Route::post('/logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::post('/sync', [Admin\DashboardController::class, 'sync'])->middleware('throttle:6,1')->name('sync');
        Route::get('/ipos', [Admin\IpoController::class, 'index'])->name('ipos.index');
        Route::get('/ipos/bulk', [Admin\IpoBulkController::class, 'index'])->name('ipos.bulk');
        Route::put('/ipos/bulk', [Admin\IpoBulkController::class, 'update'])->name('ipos.bulk.update');
        Route::get('/ipos/bulk.csv', [Admin\IpoBulkController::class, 'csv'])->name('ipos.bulk.csv');
        Route::post('/ipos/bulk/import', [Admin\IpoBulkController::class, 'import'])->name('ipos.bulk.import');
        Route::get('/ipos/{ipo}/edit', [Admin\IpoController::class, 'edit'])->name('ipos.edit');
        Route::put('/ipos/{ipo}', [Admin\IpoController::class, 'update'])->name('ipos.update');
        Route::post('/ipos/{ipo}/unlock', [Admin\IpoController::class, 'unlock'])->name('ipos.unlock');
        Route::get('/ipos/{ipo}/details', [Admin\IpoDetailController::class, 'edit'])->name('ipos.details.edit');
        Route::put('/ipos/{ipo}/details', [Admin\IpoDetailController::class, 'update'])->name('ipos.details.update');

        Route::get('/actions', [Admin\CorporateActionController::class, 'index'])->name('actions.index');
        Route::get('/actions/create', [Admin\CorporateActionController::class, 'create'])->name('actions.create');
        Route::post('/actions', [Admin\CorporateActionController::class, 'store'])->name('actions.store');
        Route::get('/actions/{offer}/edit', [Admin\CorporateActionController::class, 'edit'])->name('actions.edit');
        Route::put('/actions/{offer}', [Admin\CorporateActionController::class, 'update'])->name('actions.update');
        Route::delete('/actions/{offer}', [Admin\CorporateActionController::class, 'destroy'])->name('actions.destroy');

        Route::get('/blog', [Admin\BlogPostController::class, 'index'])->name('blog.index');
        Route::get('/blog/create', [Admin\BlogPostController::class, 'create'])->name('blog.create');
        Route::post('/blog', [Admin\BlogPostController::class, 'store'])->name('blog.store');
        Route::post('/blog/images', [Admin\BlogPostController::class, 'uploadImage'])->middleware('throttle:30,1')->name('blog.images');
        Route::put('/blog/automation', [Admin\BlogAutomationController::class, 'update'])->name('blog.automation');
        Route::post('/blog/automation/run', [Admin\BlogAutomationController::class, 'run'])->middleware('throttle:10,1')->name('blog.automation.run');
        Route::get('/blog/authors', [Admin\BlogAuthorController::class, 'index'])->name('blog.authors');
        Route::post('/blog/authors', [Admin\BlogAuthorController::class, 'store'])->name('blog.authors.store');
        Route::put('/blog/authors/{author}', [Admin\BlogAuthorController::class, 'update'])->whereNumber('author')->name('blog.authors.update');
        Route::whereNumber('post')->group(function () {
            Route::get('/blog/{post}/edit', [Admin\BlogPostController::class, 'edit'])->name('blog.edit');
            Route::put('/blog/{post}', [Admin\BlogPostController::class, 'update'])->name('blog.update');
            Route::delete('/blog/{post}', [Admin\BlogPostController::class, 'destroy'])->name('blog.destroy');
        });

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
