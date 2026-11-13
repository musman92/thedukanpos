<?php

namespace Addons\Bookings\Console;

use Addons\Bookings\Services\BookingService;
use App\Models\Tenant;
use App\Support\TenantAddons;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ExpireBookingsCommand extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Mark bookings expired 12 hours after their time has passed.';

    public function handle(): int
    {
        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) {
            $tenant->run(function () use ($tenant) {
                if (! TenantAddons::has('bookings') || ! Schema::hasTable('bookings')) {
                    return;
                }

                $count = app(BookingService::class)->expirePast();
                if ($count > 0) {
                    $this->line("{$tenant->name}: {$count} expired");
                }
            });
        });

        return self::SUCCESS;
    }
}
