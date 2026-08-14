<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MoneySource;
use App\Models\Tax;
use App\Models\Unit;

/**
 * Day-one master data for a new (or wiped) tenant.
 * Safe to re-run: uses firstOrCreate / idempotent upserts.
 */
class TenantBootstrapService
{
    public function __construct(protected FinanceService $finance) {}

    public function seedDayOneMasters(): void
    {
        $this->seedUnits();
        $tax = $this->seedTax();
        $this->seedMoneySources();
        $this->finance->seedDefaultAccounts();
        $this->seedGeneralCategory($tax?->id);
        $this->seedWalkInCustomer();
    }

    public function seedUnits(): void
    {
        foreach ([
            ['code' => 'pcs', 'name' => 'Piece'],
            ['code' => 'ctn', 'name' => 'Carton'],
            ['code' => 'kg', 'name' => 'Kilogram'],
            ['code' => 'g', 'name' => 'Gram'],
            ['code' => 'box', 'name' => 'Box'],
        ] as $unit) {
            Unit::query()->firstOrCreate(['code' => $unit['code']], [
                'name' => $unit['name'],
                'is_active' => true,
            ]);
        }
    }

    public function seedTax(): Tax
    {
        return Tax::query()->firstOrCreate(
            ['code' => 'gst18'],
            [
                'name' => 'GST 18%',
                'rate' => 18,
                'is_inclusive' => false,
                'is_active' => true,
            ],
        );
    }

    public function seedMoneySources(): void
    {
        $cash = MoneySource::query()->firstOrCreate(
            ['code' => 'cash'],
            [
                'name' => 'Cash',
                'type' => 'CASH',
                'opening_balance' => 0,
                'balance' => 0,
                'is_active' => true,
                'is_system' => true,
            ],
        );

        $ownerWithdrawal = MoneySource::query()->firstOrCreate(
            ['system_key' => MoneySource::SYSTEM_OWNER_WITHDRAWAL],
            [
                'name' => 'Owner Withdrawal',
                'code' => 'owner_withdrawal',
                'type' => 'OWNER_DRAW',
                'opening_balance' => 0,
                'balance' => 0,
                'is_active' => true,
                'is_system' => true,
            ],
        );

        // Cash is the only protected operational default. Other sources are
        // user-managed even if an older release marked them as system records.
        MoneySource::query()
            ->where('is_system', true)
            ->whereNotIn('id', [$cash->id, $ownerWithdrawal->id])
            ->update(['is_system' => false]);

        if (! $cash->is_system) {
            $cash->update(['is_system' => true]);
        }

        if (! $ownerWithdrawal->is_system) {
            $ownerWithdrawal->update(['is_system' => true]);
        }

        $branchIds = Branch::query()->where('is_active', true)->pluck('id');
        if ($branchIds->isNotEmpty()) {
            $cash->branches()->syncWithoutDetaching($branchIds->all());
            $ownerWithdrawal->branches()->syncWithoutDetaching($branchIds->all());
        }
    }

    public function seedGeneralCategory(?int $defaultTaxId = null): Category
    {
        $category = Category::query()->firstOrCreate(
            ['code' => Category::CODE_GENERAL],
            [
                'name' => 'General',
                'parent_id' => null,
                'default_tax_id' => $defaultTaxId,
                'is_active' => true,
                'is_system' => true,
            ],
        );

        if (! $category->is_system) {
            $category->is_system = true;
            $category->save();
        }

        if ($defaultTaxId && ! $category->default_tax_id) {
            $category->default_tax_id = $defaultTaxId;
            $category->save();
        }

        return $category->refresh();
    }

    public function seedWalkInCustomer(): Customer
    {
        $customer = Customer::query()->firstOrCreate(
            ['code' => Customer::CODE_WALK_IN],
            [
                'name' => 'Walk-in Customer',
                'phone' => null,
                'email' => null,
                'address' => null,
                'balance' => 0,
                'is_active' => true,
                'is_system' => true,
            ],
        );

        if (! $customer->is_system || $customer->name !== 'Walk-in Customer') {
            $customer->fill([
                'name' => 'Walk-in Customer',
                'is_system' => true,
                'is_active' => true,
            ]);
            $customer->save();
        }

        return $customer->refresh();
    }
}
