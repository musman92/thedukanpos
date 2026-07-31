<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('ledger_transactions', 'is_manual')) {
                $table->boolean('is_manual')->default(false)->after('created_by')->index();
            }
            if (! Schema::hasColumn('ledger_transactions', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Existing rows without a system reference were likely manual entries.
        if (Schema::hasColumn('ledger_transactions', 'is_manual')) {
            DB::table('ledger_transactions')
                ->whereNull('reference_type')
                ->update(['is_manual' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('ledger_transactions', 'is_manual')) {
                $drop[] = 'is_manual';
            }
            if (Schema::hasColumn('ledger_transactions', 'deleted_at')) {
                $drop[] = 'deleted_at';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
