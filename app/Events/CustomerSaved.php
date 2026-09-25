<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;

class CustomerSaved
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $addonData
     */
    public function __construct(
        public Customer $customer,
        public array $addonData = [],
    ) {}
}
