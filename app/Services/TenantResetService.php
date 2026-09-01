<?php

namespace App\Services;

use App\Models\MoneySource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TenantResetService
{
    /**
     * Deletion order respects foreign keys (children before parents).
     *
     * @var list<string>
     */
    protected const TABLE_ORDER = [
        'customer_payment_sale',
        'supplier_payment_purchase',
        'quotation_items',
        'quotations',
        'stock_damage_items',
        'stock_damages',
        'sale_return_items',
        'sale_returns',
        'purchase_return_items',
        'purchase_returns',
        'stock_transfer_items',
        'stock_transfers',
        'stock_adjustment_items',
        'stock_adjustments',
        'sale_payments',
        'sale_items',
        'sales',
        'purchase_items',
        'purchases',
        'customer_payments',
        'supplier_payments',
        'employee_payments',
        'payroll_adjustments',
        'payroll_items',
        'payroll_runs',
        'leave_requests',
        'attendance_records',
        'employee_profiles',
        'ledger_transactions',
        'money_source_transfers',
        'money_source_fund_movements',
        'shift_money_sources',
        'shifts',
        'stock_movements',
        'branch_stocks',
        'serial_numbers',
        'product_locations',
        'product_variants',
        'products',
        'variation_options',
        'variations',
        'racks',
        'sections',
        'brands',
        'categories',
        'customers',
        'suppliers',
        'activity_logs',
    ];

    /**
     * @var array<string, array{label: string, description: string, tables: list<string>}>
     */
    protected const GROUPS = [
        'sales' => [
            'label' => 'Sales & returns',
            'description' => 'POS sales, orders, payments at sale, and sale returns.',
            'tables' => [
                'customer_payment_sale',
                'sale_return_items',
                'sale_returns',
                'sale_payments',
                'sale_items',
                'sales',
            ],
        ],
        'purchases' => [
            'label' => 'Purchases & returns',
            'description' => 'Supplier purchases, purchase returns, and linked payment allocations.',
            'tables' => [
                'supplier_payment_purchase',
                'purchase_return_items',
                'purchase_returns',
                'purchase_items',
                'purchases',
            ],
        ],
        'quotations' => [
            'label' => 'Quotations',
            'description' => 'Price quotes and line items (no stock impact).',
            'tables' => [
                'quotation_items',
                'quotations',
            ],
        ],
        'inventory' => [
            'label' => 'Stock & inventory',
            'description' => 'On-hand stock, movements, adjustments, transfers, damages, and serial numbers.',
            'tables' => [
                'stock_damage_items',
                'stock_damages',
                'stock_transfer_items',
                'stock_transfers',
                'stock_adjustment_items',
                'stock_adjustments',
                'stock_movements',
                'branch_stocks',
                'serial_numbers',
                'product_locations',
            ],
        ],
        'shifts' => [
            'label' => 'Shifts',
            'description' => 'Cashier shifts and shift till snapshots.',
            'tables' => [
                'shift_money_sources',
                'shifts',
            ],
        ],
        'financial' => [
            'label' => 'Payments & ledger',
            'description' => 'Customer/supplier/employee payments, ledger entries, and till transfers. Till balances are zeroed.',
            'tables' => [
                'customer_payment_sale',
                'supplier_payment_purchase',
                'customer_payments',
                'supplier_payments',
                'employee_payments',
                'ledger_transactions',
                'money_source_transfers',
                'money_source_fund_movements',
            ],
        ],
        'catalog' => [
            'label' => 'Products & catalog',
            'description' => 'Products, variants, brands, categories, sections, racks, and variations. Stock levels for those products are cleared too. Day-one catalog masters are restored.',
            'tables' => [
                'product_locations',
                'product_variants',
                'products',
                'variation_options',
                'variations',
                'racks',
                'sections',
                'brands',
                'categories',
            ],
        ],
        'parties' => [
            'label' => 'Customers & suppliers',
            'description' => 'Customer and supplier records. Walk-in customer is recreated.',
            'tables' => [
                'customers',
                'suppliers',
            ],
        ],
        'hr' => [
            'label' => 'HR & payroll',
            'description' => 'Employee profiles, attendance, leave, payroll, and non-admin user accounts.',
            'tables' => [
                'payroll_adjustments',
                'payroll_items',
                'payroll_runs',
                'leave_requests',
                'attendance_records',
                'employee_profiles',
            ],
        ],
        'activity' => [
            'label' => 'Activity log',
            'description' => 'Audit / activity history entries.',
            'tables' => [
                'activity_logs',
            ],
        ],
    ];

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public function groupsForUi(): array
    {
        return collect(self::GROUPS)
            ->map(fn (array $group, string $key) => [
                'key' => $key,
                'label' => $group['label'],
                'description' => $group['description'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $groupKeys
     * @return array{groups: list<string>, tables: list<string>}
     */
    public function reset(Tenant $tenant, array $groupKeys): array
    {
        $groupKeys = array_values(array_unique(array_filter($groupKeys)));
        if ($groupKeys === []) {
            throw ValidationException::withMessages([
                'groups' => 'Select at least one area to reset.',
            ]);
        }

        $unknown = array_diff($groupKeys, array_keys(self::GROUPS));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'groups' => 'Invalid reset option: '.implode(', ', $unknown),
            ]);
        }

        if (in_array('catalog', $groupKeys, true) && ! in_array('inventory', $groupKeys, true)) {
            $groupKeys[] = 'inventory';
        }

        $tables = $this->resolveTables($groupKeys);

        $tenant->run(function () use ($groupKeys, $tables) {
            $this->deleteTables($tables);

            if (in_array('financial', $groupKeys, true)) {
                MoneySource::query()->update([
                    'opening_balance' => 0,
                    'balance' => 0,
                ]);
            }

            if (in_array('hr', $groupKeys, true)) {
                $this->removeNonAdminUsers();
            }

            if (
                in_array('catalog', $groupKeys, true)
                || in_array('parties', $groupKeys, true)
            ) {
                app(TenantBootstrapService::class)->seedDayOneMasters();
            }
        });

        return [
            'groups' => $groupKeys,
            'tables' => $tables,
        ];
    }

    /**
     * @param  list<string>  $groupKeys
     * @return list<string>
     */
    protected function resolveTables(array $groupKeys): array
    {
        $selected = [];

        foreach ($groupKeys as $key) {
            foreach (self::GROUPS[$key]['tables'] as $table) {
                $selected[$table] = true;
            }
        }

        $ordered = [];
        foreach (self::TABLE_ORDER as $table) {
            if (isset($selected[$table])) {
                $ordered[] = $table;
            }
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $tables
     */
    protected function deleteTables(array $tables): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    protected function removeNonAdminUsers(): void
    {
        User::query()
            ->where('username', '!=', 'admin')
            ->orderByDesc('id')
            ->each(function (User $user) {
                $user->branches()->detach();
                $user->syncRoles([]);
                $user->delete();
            });
    }
}
