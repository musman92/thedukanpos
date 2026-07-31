<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Shift;
use App\Support\BranchContext;
use App\Support\Locale;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => function () use ($request) {
                    if (! tenancy()->initialized || ! $request->user()) {
                        return null;
                    }

                    $user = $request->user();
                    $role = method_exists($user, 'getRoleNames')
                        ? $user->getRoleNames()->first()
                        : null;

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'initial' => mb_strtoupper(mb_substr($user->name ?: $user->username ?: 'U', 0, 1)),
                        'role' => $role,
                    ];
                },
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                'import_result' => fn () => $request->session()->get('import_result'),
            ],
            'i18n' => function () {
                $locale = Locale::normalize(app()->getLocale());
                $rtl = Locale::resolveRtl();

                return [
                    'locale' => $locale,
                    'dir' => $rtl ? 'rtl' : 'ltr',
                    'rtl' => $rtl,
                    'locales' => Locale::options(),
                ];
            },
            'tenant' => fn () => tenancy()->initialized ? [
                'id' => tenant('id'),
                'code' => tenant('code'),
                'name' => tenant('name'),
            ] : null,
            'branch' => function () {
                if (! tenancy()->initialized) {
                    return null;
                }

                $branch = BranchContext::ensure();

                return $branch->only(['id', 'code', 'name']);
            },
            'branches' => function () {
                if (! tenancy()->initialized) {
                    return [];
                }

                return Branch::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']);
            },
            'openShift' => function () {
                if (! tenancy()->initialized) {
                    return null;
                }

                $branch = BranchContext::branch();
                if (! $branch) {
                    return null;
                }

                $shift = Shift::query()
                    ->where('branch_id', $branch->id)
                    ->open()
                    ->latest('id')
                    ->first();

                return $shift ? [
                    'id' => $shift->id,
                    'opened_at' => optional($shift->opened_at)->toDateTimeString(),
                    'opening_cash' => $shift->opening_cash,
                ] : null;
            },
            'company' => function () {
                if (! tenancy()->initialized) {
                    return null;
                }

                try {
                    return app(\App\Services\SettingService::class)->publicConfig();
                } catch (\Throwable) {
                    return [
                        'list_page_limit' => company_page_limit(),
                    ];
                }
            },
        ];
    }
}
