<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_settings', 'public_time_mode')) {
                $table->string('public_time_mode', 20)->default('slots')->after('public_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            if (Schema::hasColumn('booking_settings', 'public_time_mode')) {
                $table->dropColumn('public_time_mode');
            }
        });
    }
};
