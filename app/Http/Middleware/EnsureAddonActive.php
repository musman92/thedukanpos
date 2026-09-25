<?php

namespace App\Http\Middleware;

use App\Support\TenantAddons;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAddonActive
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        abort_unless(tenancy()->initialized && TenantAddons::has($slug), 404);

        return $next($request);
    }
}
