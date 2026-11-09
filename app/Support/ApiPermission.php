<?php

namespace App\Support;

/**
 * Maps an /api/v1 route onto the same permission names the shop roles use.
 */
final class ApiPermission
{
    /**
     * Office lists whose permission is not {resource}.{action}.
     *
     * @var array<string, string>
     */
    private const OFFICE_EXACT = [
        'stock' => 'inventory.stock',
        'stock-low' => 'inventory.stock',
        'product-ledger' => 'inventory.stock',
        'adjustments' => 'inventory.adjust',
        'damages' => 'inventory.adjust',
        'transfers' => 'inventory.transfer',
        'activity' => 'activity-logs.index',
        'settings' => 'settings.index',
    ];

    /**
     * @var array<string, string>
     */
    private const OFFICE_MODULES = [
        'orders' => 'orders',
        'sale-returns' => 'sales-returns',
        'quotations' => 'quotations',
        'serials' => 'serials',
        'purchases' => 'purchases',
        'purchase-returns' => 'purchase-returns',
        'expenses' => 'expenses',
        'customer-payments' => 'customer-payments',
        'supplier-payments' => 'supplier-payments',
        'employee-payments' => 'employee-payments',
        'transactions' => 'transactions',
        'money-sources' => 'money-sources',
        'attendance' => 'attendance',
        'leaves' => 'leaves',
        'payroll' => 'payroll',
        'payroll-adjustments' => 'payroll-adjustments',
        'branches' => 'branches',
        'roles' => 'roles',
    ];

    /**
     * @var list<string>
     */
    private const REPORTS = [
        'sales',
        'products',
        'daily-sales',
        'payment-methods',
        'stock-on-hand',
        'receivables',
        'payables',
        'gross-margin',
        'profit-loss',
    ];

    public static function isOpen(?string $name, string $uri): bool
    {
        if ($name === 'api.v1.media.show') {
            return true;
        }

        return in_array($uri, [
            'api/v1/me',
            'api/v1/logout',
            'api/v1/profile',
            'api/v1/profile/password',
        ], true);
    }

    /**
     * Receipt header and payment methods are part of selling, not the office screens.
     */
    public static function usedByCheckout(string $name, ?string $resource): bool
    {
        if ($name === 'api.v1.settings.show') {
            return true;
        }

        return $name === 'api.v1.office.index' && $resource === 'money-sources';
    }

    /**
     * Permissions that satisfy this route. Empty means the route is not mapped.
     *
     * @return list<string>
     */
    public static function accepted(string $name, ?string $resource, ?string $report): array
    {
        if ($name === 'api.v1.reports.show') {
            $names = ['reports.index'];
            if ($report !== null && in_array($report, self::REPORTS, true)) {
                $names[] = 'reports.'.$report;
            }

            return $names;
        }

        $permission = self::required($name, $resource);

        return $permission === null ? [] : [$permission];
    }

    public static function required(string $name, ?string $resource): ?string
    {
        if ($name === 'api.v1.users.options') {
            return 'users.index';
        }

        if ($name === 'api.v1.activity.index' || $name === 'api.v1.activity.toggle') {
            return 'activity-logs.index';
        }

        if ($name === 'api.v1.subscription.index' || $name === 'api.v1.settings.show') {
            return 'settings.index';
        }

        if ($name === 'api.v1.settings.update') {
            return 'settings.update';
        }

        if ($name === 'api.v1.reports.index') {
            return 'reports.index';
        }

        if (str_starts_with($name, 'api.v1.office.')) {
            return self::office($resource, self::officeAction($name));
        }

        if (preg_match('/^api\.v1\.([a-z0-9-]+)\.(index|store|show|update|destroy)$/', $name, $matches) === 1) {
            $action = $matches[2] === 'show' ? 'index' : $matches[2];

            return $matches[1].'.'.$action;
        }

        return null;
    }

    public static function office(?string $resource, string $action): ?string
    {
        if ($resource === null || $resource === '') {
            return null;
        }

        if ($resource === 'settings') {
            return in_array($action, ['store', 'update', 'destroy'], true)
                ? 'settings.update'
                : 'settings.index';
        }

        if (isset(self::OFFICE_EXACT[$resource])) {
            return self::OFFICE_EXACT[$resource];
        }

        if (! isset(self::OFFICE_MODULES[$resource])) {
            return null;
        }

        $verb = match ($action) {
            'store' => 'store',
            'update' => 'update',
            'destroy' => 'destroy',
            default => 'index',
        };

        return self::OFFICE_MODULES[$resource].'.'.$verb;
    }

    private static function officeAction(string $name): string
    {
        return match ($name) {
            'api.v1.office.store' => 'store',
            'api.v1.office.update', 'api.v1.office.action' => 'update',
            'api.v1.office.destroy' => 'destroy',
            default => 'index',
        };
    }
}
