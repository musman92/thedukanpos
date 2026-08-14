<?php

namespace Tests\Unit;

use App\Models\MoneySource;
use PHPUnit\Framework\TestCase;

class MoneySourceTest extends TestCase
{
    public function test_protected_cash_source_is_operational(): void
    {
        $cash = new MoneySource([
            'code' => 'cash',
            'type' => 'CASH',
            'is_active' => true,
            'is_system' => true,
        ]);

        $this->assertTrue($cash->isOperational());
        $this->assertTrue($cash->isSelectableForPayment());
    }

    public function test_owner_withdrawal_bucket_is_not_operational(): void
    {
        $ownerWithdrawal = new MoneySource([
            'type' => 'OWNER_DRAW',
            'is_active' => true,
            'is_system' => true,
            'system_key' => MoneySource::SYSTEM_OWNER_WITHDRAWAL,
        ]);

        $this->assertFalse($ownerWithdrawal->isOperational());
        $this->assertFalse($ownerWithdrawal->isSelectableForPayment());
    }
}
