<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'delivery_status')) {
                $table->string('delivery_status', 32)->nullable()->after('delivery_address');
            }
            if (! Schema::hasColumn('sales', 'rider_id')) {
                $table->foreignId('rider_id')
                    ->nullable()
                    ->after('delivery_status')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'rider_id')) {
                $table->dropConstrainedForeignId('rider_id');
            }
            if (Schema::hasColumn('sales', 'delivery_status')) {
                $table->dropColumn('delivery_status');
            }
        });
    }
};
