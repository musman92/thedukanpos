<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'is_delivery')) {
                $table->boolean('is_delivery')->default(false)->after('notes');
            }
            if (! Schema::hasColumn('sales', 'delivery_charge')) {
                $table->decimal('delivery_charge', 16, 4)->default(0)->after('is_delivery');
            }
            if (! Schema::hasColumn('sales', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('delivery_charge');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            foreach (['delivery_address', 'delivery_charge', 'is_delivery'] as $col) {
                if (Schema::hasColumn('sales', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
