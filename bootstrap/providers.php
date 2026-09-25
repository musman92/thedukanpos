<?php

use App\Providers\AddonServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    AddonServiceProvider::class,
];
