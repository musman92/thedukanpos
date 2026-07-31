<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized) {
            return $next($request);
        }

        $code = $request->header('X-Tenant-Code')
            ?? $request->input('tenant_code');

        if (! $code) {
            return response()->json(['message' => 'X-Tenant-Code header is required.'], 400);
        }

        $tenant = Tenant::findByCode((string) $code);

        if (! $tenant) {
            return response()->json(['message' => 'Invalid or inactive tenant.'], 404);
        }

        tenancy()->initialize($tenant);

        return $next($request);
    }
}
