<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type', 20)->default('single')->after('name');
            $table->decimal('min_qty_alert', 16, 4)->nullable()->after('cost_per_unit');
        });

        // Existing multi-variant products become type=variant; others stay single.
        if (Schema::hasTable('product_variants')) {
            $multiIds = DB::table('product_variants')
                ->select('product_id')
                ->groupBy('product_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('product_id');

            if ($multiIds->isNotEmpty()) {
                DB::table('products')->whereIn('id', $multiIds)->update(['type' => 'variant']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['type', 'min_qty_alert']);
        });
    }
};
