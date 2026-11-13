<?php

namespace Addons\Bookings\Providers;

use Addons\Bookings\Console\ExpireBookingsCommand;
use Addons\Bookings\Listeners\MarkBookingDone;
use Addons\Bookings\Listeners\SaveBookingProductSettings;
use App\Events\ProductSaved;
use App\Events\SaleCompleted;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BookingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 2).'/resources/views', 'bookings');

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireBookingsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('bookings:expire')->hourly();
        });

        Event::listen(ProductSaved::class, SaveBookingProductSettings::class);
        Event::listen(SaleCompleted::class, MarkBookingDone::class);

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
