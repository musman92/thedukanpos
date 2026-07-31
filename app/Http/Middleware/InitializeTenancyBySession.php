<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyBySession
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->session()->get('tenant_id');

        if ($tenantId) {
            $tenant = Tenant::query()->find($tenantId);

            if (! $tenant || ! $tenant->is_active) {
                $this->clearTenantAuth($request);

                return redirect()->route('login');
            }

            if (! tenancy()->initialized) {
                tenancy()->initialize($tenant);
            }

            return $next($request);
        }

        // Auth session without tenant_id would make Laravel query landlord.users
        // (that table does not exist). Clear the broken auth identifiers first.
        $this->forgetAuthSessionKeys($request);

        return $next($request);
    }

    protected function clearTenantAuth(Request $request): void
    {
        $this->forgetAuthSessionKeys($request);
        $request->session()->forget(['tenant_id', 'tenant_code']);

        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }

    /**
     * Drop session auth keys without calling Auth::user() / logout()
     * (those would query the users table on the current connection).
     */
    protected function forgetAuthSessionKeys(Request $request): void
    {
        $guard = Auth::guard('web');
        $guard->forgetUser();

        $keys = [
            $guard->getName(),
            'password_hash_'.$guard->getName(),
        ];

        if (method_exists($guard, 'getRecallerName')) {
            $keys[] = $guard->getRecallerName();
        }

        $request->session()->forget($keys);
    }
}
