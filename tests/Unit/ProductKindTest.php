<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductKindTest extends TestCase
{
    public function test_stock_tracked_goods_affect_inventory(): void
    {
        $product = new Product(['kind' => 'goods', 'track_stock' => true]);

        $this->assertTrue($product->affectsInventory());
    }

    public function test_services_never_affect_inventory(): void
    {
        $product = new Product(['kind' => 'service', 'track_stock' => true]);

        $this->assertTrue($product->isService());
        $this->assertFalse($product->affectsInventory());
    }

    public function test_non_stock_goods_do_not_affect_inventory(): void
    {
        $product = new Product(['kind' => 'goods', 'track_stock' => false]);

        $this->assertFalse($product->affectsInventory());
    }
}
