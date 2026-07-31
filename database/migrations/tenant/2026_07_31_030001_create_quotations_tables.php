<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft'); // draft, sent, accepted, rejected, expired, converted
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 16, 4)->default(0);
            $table->decimal('tax_total', 16, 4)->default(0);
            $table->decimal('discount_total', 16, 4)->default(0);
            $table->decimal('total', 16, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'quote_date']);
            $table->index('status');
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('quantity', 16, 4);
            $table->decimal('conversion_rate', 16, 4)->default(1);
            $table->decimal('quantity_in_sale_unit', 16, 4);
            $table->decimal('unit_price', 16, 4);
            $table->decimal('discount', 16, 4)->default(0);
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->string('tax_name')->nullable();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 16, 4)->default(0);
            $table->decimal('line_total', 16, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
