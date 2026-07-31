<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Canonical permission names: {module}.{action} (e.g. products.store, pos.checkout).
 */
final class AppPermissions
{
    /**
     * Tenant modules with standard CRUD actions.
     *
     * @var list<string>
     */
    public const RESOURCE_MODULES = [
        'brands',
        'categories',
        'units',
        'variations',
        'sections',
        'racks',
        'products',
        'customers',
        'suppliers',
        'users',
        'roles',
        'branches',
        'shifts',
        'purchases',
        'purchase-returns',
        'sales-returns',
        'quotations',
        'serials',
        'accounts',
        'taxes',
        'money-sources',
        'transactions',
        'expenses',
        'supplier-payments',
        'customer-payments',
        'employee-payments',
        'attendance',
        'leaves',
        'payroll',
        'payroll-adjustments',
    ];

    public const RESOURCE_ACTIONS = ['index', 'store', 'update', 'destroy'];

    /**
     * Extra actions beyond CRUD (or modules that are not full CRUD).
     *
     * @var array<string, list<string>>
     */
    private const CUSTOM_MODULE_ACTIONS = [
        'dashboard' => ['index'],
        'pos' => ['index', 'checkout', 'receipt'],
        'settings' => ['index', 'update'],
        'inventory' => ['stock', 'adjust', 'transfer'],
        'import-export' => ['index'],
        'activity-logs' => ['index'],
        'customers' => ['receive-payment'],
        'money-sources' => ['transfer', 'owner-withdrawal', 'reports'],
        'purchases' => ['show'],
            'quotations' => ['show'],
            'orders' => ['index', 'show'],
            'reports' => [
            'index',
            'sales',
            'products',
            'daily-sales',
            'payment-methods',
            'stock-on-hand',
            'receivables',
            'payables',
            'gross-margin',
            'profit-loss',
            'shifts-z',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (self::RESOURCE_MODULES as $module) {
            foreach (self::RESOURCE_ACTIONS as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        foreach (self::CUSTOM_MODULE_ACTIONS as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    public static function forModule(string $module): array
    {
        $prefix = $module.'.';

        return array_values(array_filter(
            self::all(),
            static fn (string $p) => str_starts_with($p, $prefix)
        ));
    }

    /**
     * @return array{module: string, action: string}|null
     */
    public static function parse(?string $permission): ?array
    {
        if ($permission === null || $permission === '') {
            return null;
        }

        $lastDot = strrpos($permission, '.');
        if ($lastDot === false) {
            return ['module' => $permission, 'action' => ''];
        }

        return [
            'module' => substr($permission, 0, $lastDot),
            'action' => substr($permission, $lastDot + 1),
        ];
    }

    /**
     * Grouped for UI: module title => permission rows.
     *
     * @param  list<string>|null  $permissionNames
     * @return array<string, list<array{name: string, action: string, label: string, module_key: string}>>
     */
    public static function groupedForFrontend(?array $permissionNames = null): array
    {
        $names = $permissionNames ?? self::all();
        $grouped = [];

        foreach ($names as $name) {
            $parsed = self::parse($name);
            if (! $parsed) {
                continue;
            }
            $moduleKey = $parsed['module'];
            $action = $parsed['action'];
            $title = self::moduleTitle($moduleKey);
            $grouped[$title] ??= [];
            $grouped[$title][] = [
                'name' => $name,
                'action' => $action,
                'label' => self::actionLabel($action),
                'module_key' => $moduleKey,
            ];
        }

        ksort($grouped);

        foreach ($grouped as $title => $rows) {
            usort($rows, static fn ($a, $b) => strcmp($a['name'], $b['name']));
            $grouped[$title] = $rows;
        }

        return $grouped;
    }

    /**
     * Flat list for Inertia (array of groups).
     * Reports is listed last so its wide "Other" actions don't interrupt the CRUD matrix.
     *
     * @return list<array{title: string, permissions: list<array{name: string, action: string, label: string, module_key: string}>}>
     */
    public static function groupsForInertia(?array $permissionNames = null): array
    {
        $groups = [];
        $reports = null;

        foreach (self::groupedForFrontend($permissionNames) as $title => $permissions) {
            $group = [
                'title' => $title,
                'permissions' => $permissions,
            ];

            if ($title === 'Reports') {
                $reports = $group;
                continue;
            }

            $groups[] = $group;
        }

        if ($reports !== null) {
            $groups[] = $reports;
        }

        return $groups;
    }

    public static function moduleTitle(string $moduleKey): string
    {
        return match ($moduleKey) {
            'money-sources' => 'Money sources',
            'supplier-payments' => 'Supplier payments',
            'customer-payments' => 'Customer payments',
            'employee-payments' => 'Employee payments',
            'purchase-returns' => 'Purchase returns',
            'sales-returns' => 'Refund orders',
            'orders' => 'Orders',
            'quotations' => 'Quotations',
            'import-export' => 'Import / export',
            'activity-logs' => 'Activity log',
            'payroll-adjustments' => 'Bonuses / deductions',
            'pos' => 'POS',
            default => Str::title(str_replace('-', ' ', $moduleKey)),
        };
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'index' => 'View / list',
            'store' => 'Create',
            'update' => 'Update',
            'destroy' => 'Delete',
            'show' => 'View detail',
            'checkout' => 'Checkout',
            'receipt' => 'Receipts',
            'stock' => 'Stock on hand',
            'adjust' => 'Adjustments',
            'transfer' => 'Transfers',
            'receive-payment' => 'Receive payment',
            'owner-withdrawal' => 'Owner withdrawal',
            'reports' => 'Reports',
            'daily-sales' => 'Daily sales',
            'payment-methods' => 'Payment methods',
            'stock-on-hand' => 'Stock on hand',
            'gross-margin' => 'Gross margin',
            'profit-loss' => 'Profit & loss',
            'shifts-z' => 'Shift Z-report',
            default => Str::title(str_replace('-', ' ', $action)),
        };
    }
}
