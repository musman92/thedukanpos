<?php

namespace App\Services;

use App\Jobs\SeedTenantAdmin;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopRegistrationService
{
    public const SHOP_CODE_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @return array{shop_code:string, available:bool, reason:string}
     */
    public function inspectShopCode(string $code): array
    {
        $code = strtolower(trim($code));

        if ($code === '' || strlen($code) < 2) {
            return [
                'shop_code' => $code,
                'available' => false,
                'reason' => 'short',
            ];
        }

        if (strlen($code) > 50 || ! preg_match(self::SHOP_CODE_PATTERN, $code)) {
            return [
                'shop_code' => $code,
                'available' => false,
                'reason' => 'invalid',
            ];
        }

        $taken = Tenant::query()->where('code', $code)->exists();

        return [
            'shop_code' => $code,
            'available' => ! $taken,
            'reason' => $taken ? 'taken' : 'available',
        ];
    }

    /**
     * @param  array{name:string, email:string, password:string, shop_code:string}  $data
     */
    public function register(array $data): Tenant
    {
        $code = strtolower(trim($data['shop_code']));
        $name = trim($data['name']);
        $email = trim($data['email']);

        if (Tenant::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'shop_code' => 'This shop code is already taken.',
            ]);
        }

        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => $name,
            'email' => $email,
            'is_active' => true,
            'is_demo' => false,
            ...app(PlanService::class)->signupAttributes(),
            'billing_status' => 'trial',
            'trial_ends_at' => now()->addDays(14)->toDateString(),
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
        ]);

        try {
            (new SeedTenantAdmin(
                $tenant,
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => $data['password'],
                ],
                [
                    'shop_name' => $name,
                    'email' => $email,
                    'currency' => 'PKR',
                    'timezone' => 'Asia/Karachi',
                    'create_default_branch' => true,
                    'branch_name' => 'Main Branch',
                ],
            ))->handle();
        } catch (\Throwable $e) {
            Log::error('Shop signup failed after the tenant was created.', [
                'shop_code' => $code,
                'exception' => $e,
            ]);

            if (tenancy()->initialized) {
                tenancy()->end();
            }

            $tenant->delete();

            throw ValidationException::withMessages([
                'shop_code' => 'The shop could not be created. Try again.',
            ]);
        }

        return $tenant->fresh();
    }
}
