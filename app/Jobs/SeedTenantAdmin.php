<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\RoleBootstrapService;
use App\Support\TenantDefaultRoles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class SeedTenantAdmin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array{name?:string, email?:string|null, password?:string}  $admin
     * @param  array{
     *   shop_name?:string,
     *   email?:string,
     *   phone?:string,
     *   address?:string,
     *   tax_id?:string,
     *   currency?:string,
     *   timezone?:string,
     *   create_default_branch?:bool,
     *   branch_name?:string
     * }  $defaults
     */
    public function __construct(
        protected TenantWithDatabase $tenant,
        protected array $admin = [],
        protected array $defaults = [],
    ) {}

    public function handle(): void
    {
        $password = (string) ($this->admin['password'] ?? 'password');
        $name = trim((string) ($this->admin['name'] ?? 'Administrator')) ?: 'Administrator';
        $email = isset($this->admin['email']) && $this->admin['email'] !== ''
            ? (string) $this->admin['email']
            : null;

        $this->tenant->run(function () use ($password, $name, $email) {
            app(RoleBootstrapService::class)->syncDefaultRoles();

            $admin = User::query()->firstOrCreate(
                ['username' => 'admin'],
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'can_login' => true,
                ],
            );

            $admin->fill([
                'name' => $name,
                'email' => $email,
                'can_login' => true,
                'is_active' => true,
            ]);

            if ($admin->wasRecentlyCreated === false && $password !== '') {
                $admin->password = Hash::make($password);
            }

            $admin->save();
            $admin->syncRoles([TenantDefaultRoles::ADMINISTRATOR]);
        });

        (new SeedTenantDefaults($this->tenant, $this->defaults))->handle();
    }
}
