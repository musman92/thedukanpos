<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;

class ProductSaved
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $addonData
     */
    public function __construct(
        public Product $product,
        public array $addonData = [],
    ) {}
}
