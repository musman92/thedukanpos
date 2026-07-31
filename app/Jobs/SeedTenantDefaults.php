<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\MoneySource;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class SeedTenantDefaults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
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
     * }  $options
     */
    public function __construct(
        protected TenantWithDatabase $tenant,
        protected array $options = [],
    ) {}

    public function handle(): void
    {
        $this->tenant->run(function () {
            $createBranch = array_key_exists('create_default_branch', $this->options)
                ? (bool) $this->options['create_default_branch']
                : true;

            $branch = null;
            if ($createBranch) {
                $branchName = trim((string) ($this->options['branch_name'] ?? '')) ?: 'Main Branch';
                $branch = Branch::query()->firstOrCreate(
                    ['code' => 'main'],
                    ['name' => $branchName, 'is_active' => true],
                );
            }

            foreach ([
                ['code' => 'pcs', 'name' => 'Piece'],
                ['code' => 'ctn', 'name' => 'Carton'],
                ['code' => 'kg', 'name' => 'Kilogram'],
                ['code' => 'g', 'name' => 'Gram'],
                ['code' => 'box', 'name' => 'Box'],
            ] as $unit) {
                Unit::query()->firstOrCreate(['code' => $unit['code']], [
                    'name' => $unit['name'],
                    'is_active' => true,
                ]);
            }

            Tax::query()->firstOrCreate(
                ['code' => 'exempt'],
                ['name' => 'Exempt', 'rate' => 0, 'is_inclusive' => false, 'is_active' => true],
            );

            Tax::query()->firstOrCreate(
                ['code' => 'gst5'],
                ['name' => 'GST 5%', 'rate' => 5, 'is_inclusive' => false, 'is_active' => true],
            );

            Tax::query()->firstOrCreate(
                ['code' => 'gst18'],
                ['name' => 'GST 18%', 'rate' => 18, 'is_inclusive' => false, 'is_active' => true],
            );

            MoneySource::query()->firstOrCreate(
                ['code' => 'cash'],
                [
                    'name' => 'Cash',
                    'type' => 'CASH',
                    'opening_balance' => 0,
                    'balance' => 0,
                    'is_active' => true,
                    'is_system' => false,
                ],
            );

            MoneySource::query()->firstOrCreate(
                ['code' => 'card'],
                [
                    'name' => 'Card',
                    'type' => 'BANK',
                    'opening_balance' => 0,
                    'balance' => 0,
                    'is_active' => true,
                    'is_system' => false,
                ],
            );

            MoneySource::query()->firstOrCreate(
                ['system_key' => MoneySource::SYSTEM_OWNER_WITHDRAWAL],
                [
                    'name' => 'Owner Withdrawal',
                    'code' => 'owner_withdrawal',
                    'type' => 'OWNER_DRAW',
                    'opening_balance' => 0,
                    'balance' => 0,
                    'is_active' => true,
                    'is_system' => true,
                ],
            );

            if ($branch) {
                User::query()->where('username', 'admin')->update(['branch_id' => $branch->id]);

                $admin = User::query()->where('username', 'admin')->first();
                if ($admin) {
                    $admin->branches()->syncWithoutDetaching([
                        $branch->id => ['is_primary' => true],
                    ]);
                }
            }

            $settings = app(SettingService::class);
            $settings->seedDefaults();

            $currency = strtoupper(trim((string) ($this->options['currency'] ?? 'PKR'))) ?: 'PKR';
            $symbol = SettingService::CURRENCY_SYMBOLS[$currency] ?? $currency;

            $settings->update([
                'shop_name' => (string) ($this->options['shop_name'] ?? $this->tenant->name ?? ''),
                'email' => (string) ($this->options['email'] ?? ''),
                'phone' => (string) ($this->options['phone'] ?? ''),
                'address' => (string) ($this->options['address'] ?? ''),
                'tax_id' => (string) ($this->options['tax_id'] ?? ''),
                'currency' => $currency,
                'currency_symbol' => $symbol,
                'timezone' => (string) ($this->options['timezone'] ?? config('app.timezone', 'UTC')),
            ]);
        });
    }
}
