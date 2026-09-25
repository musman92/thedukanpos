<?php

namespace Addons\YourAddon\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Template provider — rename namespace/class when copying.
 *
 * Routes are registered at app boot and protected per tenant by addon.active.
 * AddonProvisionService owns install/remove migrations.
 */
class YourAddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind addon services here.
    }

    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->registerRoutes();
        // $this->loadTranslationsFrom(...);
        // Event::listen(...);
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth', 'tenancy.session', 'addon.active:your-addon'])
            ->prefix('admin')
            ->name('admin.')
            ->group(dirname(__DIR__, 2).'/routes/admin.php');

        $posRoutes = dirname(__DIR__, 2).'/routes/pos.php';
        if (is_file($posRoutes)) {
            Route::middleware(['web', 'auth', 'tenancy.session', 'addon.active:your-addon'])
                ->prefix('pos')
                ->name('pos.')
                ->group($posRoutes);
        }
    }
}
