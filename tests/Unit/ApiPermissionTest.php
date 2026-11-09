<?php

namespace Tests\Unit;

use App\Support\ApiPermission;
use PHPUnit\Framework\TestCase;

class ApiPermissionTest extends TestCase
{
    public function test_resource_show_uses_the_list_permission(): void
    {
        $this->assertSame('brands.index', ApiPermission::required('api.v1.brands.show', null));
        $this->assertSame('products.store', ApiPermission::required('api.v1.products.store', null));
        $this->assertSame('users.index', ApiPermission::required('api.v1.users.options', null));
    }

    public function test_office_lists_use_the_shop_permission_names(): void
    {
        $this->assertSame('inventory.stock', ApiPermission::office('stock', 'index'));
        $this->assertSame('inventory.adjust', ApiPermission::office('adjustments', 'store'));
        $this->assertSame('inventory.transfer', ApiPermission::office('transfers', 'update'));
        $this->assertSame('sales-returns.index', ApiPermission::office('sale-returns', 'index'));
        $this->assertSame('sales-returns.store', ApiPermission::office('sale-returns', 'store'));
        $this->assertSame('settings.index', ApiPermission::required('api.v1.subscription.index', null));
        $this->assertSame('activity-logs.index', ApiPermission::required('api.v1.activity.toggle', null));
    }

    public function test_a_report_opens_with_either_the_list_or_that_report(): void
    {
        $this->assertSame(
            ['reports.index', 'reports.sales'],
            ApiPermission::accepted('api.v1.reports.show', null, 'sales'),
        );
    }

    public function test_profile_and_media_stay_open_and_checkout_can_read_payment_setup(): void
    {
        $this->assertTrue(ApiPermission::isOpen(null, 'api/v1/me'));
        $this->assertTrue(ApiPermission::isOpen('api.v1.media.show', 'api/v1/media/{path}'));
        $this->assertFalse(ApiPermission::isOpen('api.v1.products.index', 'api/v1/products'));
        $this->assertTrue(ApiPermission::usedByCheckout('api.v1.settings.show', null));
        $this->assertTrue(ApiPermission::usedByCheckout('api.v1.office.index', 'money-sources'));
        $this->assertFalse(ApiPermission::usedByCheckout('api.v1.settings.update', null));
        $this->assertSame('unknown.index', ApiPermission::required('api.v1.unknown.index', null));
        $this->assertNull(ApiPermission::required('api.v1.not-a-route', null));
    }
}
