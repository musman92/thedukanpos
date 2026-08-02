<?php

namespace Addons\Installments\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Installments addon bootstrap.
 *
 * Loaded only when this addon is Active for the current tenant (Addon Manager TBD).
 * Do not register heavy feature logic here until product decisions are locked.
 */
class InstallmentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $base = dirname(__DIR__, 2);

        $this->loadMigrationsFrom($base.'/database/migrations');
        $this->registerRoutes($base);
    }

    protected function registerRoutes(string $base): void
    {
        Route::middleware(['web', 'auth', 'tenancy.session'])
            ->prefix('admin')
            ->name('admin.')
            ->group($base.'/routes/admin.php');

        $posRoutes = $base.'/routes/pos.php';
        if (is_file($posRoutes)) {
            Route::middleware(['web', 'auth', 'tenancy.session'])
                ->prefix('pos')
                ->name('pos.')
                ->group($posRoutes);
        }
    }
}
