<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\RoleBootstrapService;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync {--tenant= : Tenant id or code (default: all)}';

    protected $description = 'Sync canonical permissions and seed default roles for tenant(s)';

    public function handle(RoleBootstrapService $bootstrap): int
    {
        $filter = $this->option('tenant');

        $tenants = Tenant::query()
            ->when($filter, function ($query) use ($filter) {
                $query->where('id', $filter)->orWhere('code', $filter);
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($bootstrap, $tenant) {
                $bootstrap->syncDefaultRoles();
                $this->info("Synced permissions for tenant {$tenant->code} ({$tenant->id}).");
            });
        }

        return self::SUCCESS;
    }
}
