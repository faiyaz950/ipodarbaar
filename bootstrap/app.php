<?php

use App\Http\Middleware\RefreshIpoData;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            RefreshIpoData::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        // Mail providers' one-click unsubscribe posts without a token; the URL is signed instead.
        // The IPO relay authenticates with a bearer token instead.
        $middleware->validateCsrfTokens(except: ['unsubscribe/*', 'internal/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
