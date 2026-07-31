<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\DemoSeedService;
use Illuminate\Console\Command;

class SeedTenantDemoCommand extends Command
{
    protected $signature = 'dukan:seed-demo
        {tenant : Tenant id or code}
        {--initiated-by= : Platform user id that started the seed}';

    protected $description = 'Wipe and seed realistic demo data for a demo tenant (queued/background safe)';

    public function handle(DemoSeedService $demoSeed): int
    {
        $key = (string) $this->argument('tenant');
        $tenant = Tenant::query()->where('id', $key)->orWhere('code', $key)->first();

        if (! $tenant) {
            $this->error("Tenant [{$key}] not found.");

            return self::FAILURE;
        }

        if (! $tenant->is_demo) {
            $this->error("Tenant [{$tenant->code}] is not marked as demo.");

            return self::FAILURE;
        }

        $initiatedBy = $this->option('initiated-by');
        $initiatedBy = $initiatedBy !== null && $initiatedBy !== '' ? (int) $initiatedBy : null;

        $this->info("Seeding demo data for {$tenant->code}…");
        $demoSeed->run($tenant, $initiatedBy);
        $this->info('Done.');

        return self::SUCCESS;
    }
}
