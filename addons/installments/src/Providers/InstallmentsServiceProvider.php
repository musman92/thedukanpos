<?php

namespace Addons\Installments\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Installments addon bootstrap.
 *
 * Routes are registered at app boot and protected per tenant by addon.active.
 * Migrations are owned by AddonProvisionService, not normal tenant:migrate.
 */
class InstallmentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $base = dirname(__DIR__, 2);

        $this->registerRoutes($base);
    }

    protected function registerRoutes(string $base): void
    {
        Route::middleware(['web', 'auth', 'tenancy.session', 'addon.active:installments'])
            ->prefix('admin')
            ->name('admin.')
            ->group($base.'/routes/admin.php');

        $posRoutes = $base.'/routes/pos.php';
        if (is_file($posRoutes)) {
            Route::middleware(['web', 'auth', 'tenancy.session', 'addon.active:installments'])
                ->prefix('pos')
                ->name('pos.')
                ->group($posRoutes);
        }
    }
}
