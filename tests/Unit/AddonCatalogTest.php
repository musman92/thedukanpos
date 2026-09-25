<?php

namespace Tests\Unit;

use App\Support\AddonCatalog;
use Tests\TestCase;

class AddonCatalogTest extends TestCase
{
    public function test_catalog_discovers_composable_manifests(): void
    {
        $bookings = AddonCatalog::find('bookings');

        $this->assertNotNull($bookings);
        $this->assertSame('Addons\\Bookings\\Providers\\BookingsServiceProvider', $bookings['provider']);
        $this->assertSame([], $bookings['requires']);
        $this->assertSame([], $bookings['conflicts']);
        $this->assertSame('orders', $bookings['capabilities']['checkout.surface']);
        $this->assertNotEmpty($bookings['slots']['product.form']);
    }

    public function test_template_is_not_in_catalog(): void
    {
        $this->assertNull(AddonCatalog::find('_template'));
    }
}
