<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_items')) {
            return;
        }

        if (! Schema::hasColumn('purchase_items', 'expiry_date')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->date('expiry_date')->nullable()->after('cost_per_sale_unit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_items') && Schema::hasColumn('purchase_items', 'expiry_date')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->dropColumn('expiry_date');
            });
        }
    }
};
