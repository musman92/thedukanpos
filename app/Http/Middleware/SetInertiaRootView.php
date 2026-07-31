<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SetInertiaRootView
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('platform') || $request->is('platform/*')) {
            Inertia::setRootView('app');
            view()->share('viteEntry', 'resources/js/platform.jsx');
        } elseif ($request->is('admin') || $request->is('admin/*')) {
            Inertia::setRootView('app');
            view()->share('viteEntry', 'resources/js/admin.jsx');
        } elseif ($request->is('login') || $request->is('forgot-password') || $request->is('reset-password') || $request->is('reset-password/*')) {
            Inertia::setRootView('app');
            view()->share('viteEntry', 'resources/js/auth.jsx');
        } elseif ($request->is('pos') || $request->is('pos/*')) {
            Inertia::setRootView('app');
            view()->share('viteEntry', 'resources/js/pos.jsx');
        } else {
            Inertia::setRootView('app');
            view()->share('viteEntry', 'resources/js/admin.jsx');
        }

        return $next($request);
    }
}
