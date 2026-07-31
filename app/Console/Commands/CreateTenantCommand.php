<?php

namespace App\Console\Commands;

use App\Jobs\SeedTenantAdmin;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTenantCommand extends Command
{
    protected $signature = 'dukan:create-tenant
        {code : Unique shop code (e.g. shop1)}
        {--name= : Display name}
        {--password=password : Initial admin password}';

    protected $description = 'Provision a tenant database and seed admin@{code}';

    public function handle(): int
    {
        $code = strtolower((string) $this->argument('code'));

        if (! preg_match('/^[a-z0-9-]+$/', $code)) {
            $this->error('Code must be lowercase letters, numbers, and hyphens only.');

            return self::FAILURE;
        }

        if (Tenant::query()->where('code', $code)->exists()) {
            $this->error("Tenant code [{$code}] already exists.");

            return self::FAILURE;
        }

        $name = $this->option('name') ?: Str::title(str_replace('-', ' ', $code));
        $password = (string) $this->option('password');

        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);

        // DB create + migrate run via TenantCreated pipeline.
        // Seed admin after migrations complete.
        (new SeedTenantAdmin($tenant, ['password' => $password]))->handle();

        $this->info("Tenant [{$code}] created.");
        $this->line("Login: admin@{$code}");
        $this->line("Password: {$password}");

        return self::SUCCESS;
    }
}
