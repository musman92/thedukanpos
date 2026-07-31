<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_payment_purchase')) {
            return;
        }

        Schema::create('supplier_payment_purchase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->decimal('amount', 14, 4);
            $table->timestamps();

            $table->unique(['supplier_payment_id', 'purchase_id'], 'spp_payment_purchase_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_purchase');
    }
};
