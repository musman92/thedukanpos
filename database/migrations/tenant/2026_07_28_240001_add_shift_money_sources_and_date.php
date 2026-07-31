<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->date('shift_date')->nullable()->after('branch_id');
        });

        Schema::create('shift_money_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('money_source_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_balance', 16, 4)->default(0);
            $table->decimal('closing_balance', 16, 4)->nullable();
            $table->decimal('expected_balance', 16, 4)->default(0);
            $table->decimal('difference', 16, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['shift_id', 'money_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_money_sources');

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('shift_date');
        });
    }
};
