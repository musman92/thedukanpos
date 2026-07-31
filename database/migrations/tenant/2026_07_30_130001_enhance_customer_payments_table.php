<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_payments', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('shift_id');
            }
            if (! Schema::hasColumn('customer_payments', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('customer_payments', 'payment_date')) {
            foreach (DB::table('customer_payments')->whereNull('payment_date')->orderBy('id')->get() as $row) {
                DB::table('customer_payments')->where('id', $row->id)->update([
                    'payment_date' => $row->created_at
                        ? substr((string) $row->created_at, 0, 10)
                        : now()->toDateString(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('customer_payments', 'payment_date')) {
                $drop[] = 'payment_date';
            }
            if (Schema::hasColumn('customer_payments', 'deleted_at')) {
                $drop[] = 'deleted_at';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
