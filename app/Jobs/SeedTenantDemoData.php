<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\DemoSeedService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SeedTenantDemoData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;

    public int $tries = 1;

    public function __construct(
        public string $tenantId,
        public ?int $initiatedBy = null,
    ) {}

    public function handle(DemoSeedService $demoSeed): void
    {
        set_time_limit(0);

        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant) {
            return;
        }

        $demoSeed->run($tenant, $this->initiatedBy);
    }

    public function failed(?Throwable $exception): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant) {
            return;
        }

        app(DemoSeedService::class)->markStatus(
            $tenant,
            DemoSeedService::STATUS_FAILED,
            $exception?->getMessage() ?: 'Demo seed failed.',
            $this->initiatedBy,
        );
    }
}
