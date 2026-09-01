<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('shift_id');
            $table->index(['branch_id', 'business_date']);
        });

        DB::table('sales')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('sales')
                        ->where('id', $row->id)
                        ->update([
                            'business_date' => \Illuminate\Support\Carbon::parse($row->created_at)->toDateString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'business_date']);
            $table->dropColumn('business_date');
        });
    }
};
