<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_settings') || ! Schema::hasColumn('booking_settings', 'sales_ui')) {
            return;
        }

        Schema::table('booking_settings', function (Blueprint $table) {
            $table->dropColumn('sales_ui');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_settings') || Schema::hasColumn('booking_settings', 'sales_ui')) {
            return;
        }

        Schema::table('booking_settings', function (Blueprint $table) {
            $table->string('sales_ui', 20)->default('both');
        });
    }
};
