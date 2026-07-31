<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('money_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('money_sources', 'exclude_from_dashboard_profit')) {
                $table->boolean('exclude_from_dashboard_profit')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('money_sources', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('exclude_from_dashboard_profit');
            }
            if (! Schema::hasColumn('money_sources', 'system_key')) {
                $table->string('system_key', 64)->nullable()->unique()->after('is_system');
            }
            if (! Schema::hasColumn('money_sources', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Normalize types to FoodPOS values: CASH, BANK, APP
        foreach (DB::table('money_sources')->orderBy('id')->get() as $row) {
            $lower = strtolower((string) $row->type);
            $mapped = match ($lower) {
                'cash' => 'CASH',
                'bank', 'card' => 'BANK',
                'wallet', 'app', 'other' => 'APP',
                'owner_draw' => 'OWNER_DRAW',
                default => in_array(strtoupper((string) $row->type), ['CASH', 'BANK', 'APP', 'OWNER_DRAW'], true)
                    ? strtoupper((string) $row->type)
                    : 'CASH',
            };

            if ($mapped !== $row->type) {
                DB::table('money_sources')->where('id', $row->id)->update(['type' => $mapped]);
            }
        }

        if (! Schema::hasTable('branch_money_sources')) {
            Schema::create('branch_money_sources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('money_source_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['branch_id', 'money_source_id']);
            });
        }

        $now = now();
        $branchIds = DB::table('branches')->where('is_active', true)->pluck('id');
        $sourceIds = DB::table('money_sources')->whereNull('deleted_at')->pluck('id');

        foreach ($sourceIds as $sourceId) {
            foreach ($branchIds as $branchId) {
                $exists = DB::table('branch_money_sources')
                    ->where('branch_id', $branchId)
                    ->where('money_source_id', $sourceId)
                    ->exists();

                if (! $exists) {
                    DB::table('branch_money_sources')->insert([
                        'branch_id' => $branchId,
                        'money_source_id' => $sourceId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        if (! Schema::hasTable('money_source_fund_movements')) {
            Schema::create('money_source_fund_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('from_money_source_id')->constrained('money_sources')->restrictOnDelete();
                $table->foreignId('to_money_source_id')->constrained('money_sources')->restrictOnDelete();
                $table->string('movement_type', 32)->default('owner_withdrawal');
                $table->decimal('amount', 16, 4);
                $table->date('movement_date');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
                $table->index(['movement_date', 'movement_type']);
            });
        }

        $exists = DB::table('money_sources')
            ->where('system_key', 'owner_withdrawal')
            ->exists();

        if (! $exists) {
            $ownerId = DB::table('money_sources')->insertGetId([
                'name' => 'Owner Withdrawal',
                'code' => 'owner_withdrawal',
                'type' => 'OWNER_DRAW',
                'opening_balance' => 0,
                'balance' => 0,
                'is_active' => true,
                'exclude_from_dashboard_profit' => false,
                'is_system' => true,
                'system_key' => 'owner_withdrawal',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($branchIds as $branchId) {
                DB::table('branch_money_sources')->insert([
                    'branch_id' => $branchId,
                    'money_source_id' => $ownerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('money_source_fund_movements')) {
            Schema::dropIfExists('money_source_fund_movements');
        }
        if (Schema::hasTable('branch_money_sources')) {
            Schema::dropIfExists('branch_money_sources');
        }

        Schema::table('money_sources', function (Blueprint $table) {
            $drop = [];
            foreach (['exclude_from_dashboard_profit', 'is_system', 'system_key', 'deleted_at'] as $col) {
                if (Schema::hasColumn('money_sources', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
