<?php

use App\Http\Middleware\EnsureAddonActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitializeTenancyByHeader;
use App\Http\Middleware\InitializeTenancyByPathCode;
use App\Http\Middleware\InitializeTenancyBySession;
use App\Http\Middleware\SetInertiaRootView;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/admin');

        // Tenancy must run after the session starts and before Authenticate /
        // HandleInertiaRequests resolve Auth::user() (users live in tenant DBs).
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: InitializeTenancyBySession::class,
        );

        $middleware->web(append: [
            SetInertiaRootView::class,
            InitializeTenancyBySession::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenancy.session' => InitializeTenancyBySession::class,
            'tenancy.header' => InitializeTenancyByHeader::class,
            'tenancy.path' => InitializeTenancyByPathCode::class,
            'addon.active' => EnsureAddonActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
