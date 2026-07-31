<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
            $table->string('phone', 50)->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->string('tax_id')->nullable()->after('address');
            $table->string('currency', 3)->default('PKR')->after('tax_id');
            $table->string('timezone')->default('Asia/Karachi')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone', 'address', 'tax_id', 'currency', 'timezone']);
        });
    }
};
