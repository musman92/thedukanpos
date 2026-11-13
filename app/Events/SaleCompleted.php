<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;

class SaleCompleted
{
    use Dispatchable;

    public function __construct(public Sale $sale) {}
}
