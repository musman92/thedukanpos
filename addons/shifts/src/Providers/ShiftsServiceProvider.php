<?php

namespace Addons\Shifts\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShiftsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth', 'tenancy.session', 'addon.active:shifts'])
            ->prefix('admin')
            ->name('admin.')
            ->group(dirname(__DIR__, 2).'/routes/admin.php');
    }
}
