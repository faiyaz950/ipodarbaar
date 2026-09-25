<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IpoController;
use App\Http\Controllers\LogoController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
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
Route::get('/ipo/{ipo}', [IpoController::class, 'show'])->name('ipos.show');

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
Route::get('/logos/{file}', LogoController::class)->where('file', '[a-z0-9-]+\.webp')->withoutMiddleware(RefreshIpoData::class)->name('logos');
Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');

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
    });
});
