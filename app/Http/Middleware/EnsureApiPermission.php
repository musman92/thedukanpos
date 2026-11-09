<?php

namespace App\Http\Middleware;

use App\Support\ApiPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $name = $route?->getName();
        $uri = trim((string) ($route?->uri() ?? ''), '/');

        if (ApiPermission::isOpen(is_string($name) ? $name : null, $uri)) {
            return $next($request);
        }

        $resource = $route?->parameter('resource');
        $report = $route?->parameter('report');
        $accepted = ApiPermission::accepted(
            is_string($name) ? $name : '',
            is_string($resource) ? $resource : null,
            is_string($report) ? $report : null,
        );

        $user = $request->user();
        if ($user !== null) {
            foreach ($accepted as $permission) {
                if ($user->can($permission)) {
                    return $next($request);
                }
            }

            if (
                $user->can('pos.index')
                && ApiPermission::usedByCheckout(
                    is_string($name) ? $name : '',
                    is_string($resource) ? $resource : null,
                )
            ) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'You do not have permission to do that.',
        ], 403);
    }
}
