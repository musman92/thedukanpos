<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_payments', 'kind')) {
                $table->string('kind', 20)->default('wage')->after('branch_id');
            }
        });

        if (Schema::hasColumn('employee_payments', 'kind')) {
            DB::table('employee_payments')
                ->whereNotNull('payroll_item_id')
                ->where(function ($q) {
                    $q->whereNull('kind')->orWhere('kind', 'wage');
                })
                ->update(['kind' => 'payroll']);
        }
    }

    public function down(): void
    {
        Schema::table('employee_payments', function (Blueprint $table) {
            if (Schema::hasColumn('employee_payments', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};
