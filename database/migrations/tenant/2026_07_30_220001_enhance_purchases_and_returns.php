<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (! Schema::hasColumn('purchases', 'discount_total')) {
                    $table->decimal('discount_total', 16, 4)->default(0)->after('tax_total');
                }
                if (! Schema::hasColumn('purchases', 'paid_amount')) {
                    $table->decimal('paid_amount', 16, 4)->default(0)->after('total');
                }
                if (! Schema::hasColumn('purchases', 'returned_amount')) {
                    $table->decimal('returned_amount', 16, 4)->default(0)->after('paid_amount');
                }
                if (! Schema::hasColumn('purchases', 'payment_status')) {
                    $table->string('payment_status')->default('pending')->after('returned_amount');
                }
                if (! Schema::hasColumn('purchases', 'money_source_id')) {
                    $table->foreignId('money_source_id')->nullable()->after('payment_status')
                        ->constrained('money_sources')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('purchase_items') && ! Schema::hasColumn('purchase_items', 'quantity_returned')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->decimal('quantity_returned', 16, 4)->default(0)->after('quantity');
            });
        }

        if (Schema::hasTable('purchase_returns')) {
            Schema::table('purchase_returns', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_returns', 'settlement_type')) {
                    $table->string('settlement_type')->default('reduce_payable')->after('total');
                }
                if (! Schema::hasColumn('purchase_returns', 'payable_reduction_amount')) {
                    $table->decimal('payable_reduction_amount', 16, 4)->default(0)->after('settlement_type');
                }
                if (! Schema::hasColumn('purchase_returns', 'credit_amount')) {
                    $table->decimal('credit_amount', 16, 4)->default(0)->after('payable_reduction_amount');
                }
            });
        }

        if (Schema::hasTable('purchase_return_items') && ! Schema::hasColumn('purchase_return_items', 'purchase_item_id')) {
            Schema::table('purchase_return_items', function (Blueprint $table) {
                $table->foreignId('purchase_item_id')->nullable()->after('purchase_return_id')
                    ->constrained('purchase_items')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_return_items') && Schema::hasColumn('purchase_return_items', 'purchase_item_id')) {
            Schema::table('purchase_return_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('purchase_item_id');
            });
        }

        if (Schema::hasTable('purchase_returns')) {
            Schema::table('purchase_returns', function (Blueprint $table) {
                $cols = array_filter(['credit_amount', 'payable_reduction_amount', 'settlement_type'], fn ($c) => Schema::hasColumn('purchase_returns', $c));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('purchase_items') && Schema::hasColumn('purchase_items', 'quantity_returned')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->dropColumn('quantity_returned');
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (Schema::hasColumn('purchases', 'money_source_id')) {
                    $table->dropConstrainedForeignId('money_source_id');
                }
                $cols = array_filter(
                    ['payment_status', 'returned_amount', 'paid_amount', 'discount_total'],
                    fn ($c) => Schema::hasColumn('purchases', $c),
                );
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
