<?php

namespace Addons\Bookings\Providers;

use Addons\Bookings\Listeners\SaveBookingProductSettings;
use Addons\Bookings\Models\BookingSetting;
use App\Events\ProductSaved;
use App\Support\AddonRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class BookingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ProductSaved::class, SaveBookingProductSettings::class);
        app(AddonRegistry::class)->registerCapability(
            'bookings',
            'checkout.surface',
            fn () => Schema::hasTable('booking_settings')
                ? BookingSetting::current()->sales_ui
                : 'orders',
        );

        if ($this->app->routesAreCached()) {
            return;
        }

        $base = dirname(__DIR__, 2);

        Route::middleware(['web', 'auth', 'tenancy.session', 'addon.active:bookings'])
            ->prefix('admin')
            ->name('admin.')
            ->group($base.'/routes/admin.php');

        Route::middleware(['api', 'tenancy.header', 'auth:sanctum', 'addon.active:bookings'])
            ->prefix('api/v1')
            ->name('api.v1.')
            ->group($base.'/routes/api.php');

        Route::middleware(['web', 'tenancy.path', 'addon.active:bookings', 'throttle:20,1'])
            ->group($base.'/routes/public.php');
    }
}
