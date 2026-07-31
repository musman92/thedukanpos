<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('variation_id')
                ->nullable()
                ->after('category_id')
                ->constrained('variations')
                ->nullOnDelete();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('variation_option_id')
                ->nullable()
                ->after('product_id')
                ->constrained('variation_options')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variation_option_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variation_id');
        });
    }
};
