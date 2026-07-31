<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_login')->default(true)->after('is_active');
            $table->string('phone', 50)->nullable()->after('email');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->text('address')->nullable()->after('email');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('contact_person')->nullable()->after('name');
            $table->text('notes')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_login', 'phone']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('address');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'notes']);
        });
    }
};
