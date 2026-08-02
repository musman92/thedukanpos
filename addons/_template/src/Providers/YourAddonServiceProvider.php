<?php

namespace Addons\YourAddon\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Template provider — rename namespace/class when copying.
 *
 * Loaded only when this addon is Active for the current tenant (runtime TBD).
 */
class YourAddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind addon services here.
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');
        $this->registerRoutes();
        // $this->loadTranslationsFrom(...);
        // Event::listen(...);
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth', 'tenancy.session'])
            ->prefix('admin')
            ->name('admin.')
            ->group(dirname(__DIR__, 2).'/routes/admin.php');

        $posRoutes = dirname(__DIR__, 2).'/routes/pos.php';
        if (is_file($posRoutes)) {
            Route::middleware(['web', 'auth', 'tenancy.session'])
                ->prefix('pos')
                ->name('pos.')
                ->group($posRoutes);
        }
    }
}
