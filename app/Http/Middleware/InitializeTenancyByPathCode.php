<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByPathCode
{
    public function handle(Request $request, Closure $next): Response
    {
        $code = strtolower(trim((string) $request->route('tenant_code')));
        $tenant = Tenant::findByCode($code);
        abort_unless($tenant, 404);

        if (tenancy()->initialized && (string) tenant('id') !== (string) $tenant->id) {
            tenancy()->end();
        }

        if (! tenancy()->initialized) {
            tenancy()->initialize($tenant);
        }

        return $next($request);
    }
}
