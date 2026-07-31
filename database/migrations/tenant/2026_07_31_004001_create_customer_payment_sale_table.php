<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_payment_sale')) {
            return;
        }

        Schema::create('customer_payment_sale', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_payment_id')->constrained('customer_payments')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->decimal('amount', 14, 4);
            $table->timestamps();

            $table->unique(['customer_payment_id', 'sale_id'], 'cps_payment_sale_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_sale');
    }
};
