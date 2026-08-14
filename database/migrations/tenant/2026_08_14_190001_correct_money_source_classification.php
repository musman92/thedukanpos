<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Owner Withdrawal is the only non-operational bucket. Cash remains a
        // protected default, while legacy Card/Bank/App sources become normal
        // user-managed operational sources without changing their balances.
        DB::table('money_sources')
            ->where('is_system', true)
            ->where(function ($query) {
                $query->whereNull('code')
                    ->orWhere('code', '!=', 'cash');
            })
            ->where(function ($query) {
                $query->whereNull('system_key')
                    ->orWhere('system_key', '!=', 'owner_withdrawal');
            })
            ->update(['is_system' => false]);

        DB::table('money_sources')
            ->where('code', 'cash')
            ->update(['is_system' => true]);

        DB::table('money_sources')
            ->where('system_key', 'owner_withdrawal')
            ->update(['is_system' => true]);
    }

    public function down(): void
    {
        DB::table('money_sources')
            ->whereIn('code', ['cash', 'card'])
            ->update(['is_system' => true]);
    }
};
