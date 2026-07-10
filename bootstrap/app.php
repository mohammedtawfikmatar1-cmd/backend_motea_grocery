<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v1.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: SetLocale::class);
        $middleware->api(append: SetLocale::class);

        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->expectsJson() ? null : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
