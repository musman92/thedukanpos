<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->string('short_code')->unique();
                $table->string('barcode')->nullable()->unique();
                $table->string('sku')->nullable();
                $table->foreignId('purchase_unit_id')->constrained('units');
                $table->foreignId('sale_unit_id')->constrained('units');
                $table->decimal('conversion_rate', 16, 4)->default(1);
                $table->decimal('sale_price', 16, 4)->default(0);
                $table->decimal('cost_per_unit', 16, 4)->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $this->addVariantColumn('branch_stocks');
        $this->addVariantColumn('product_locations');
        $this->addVariantColumn('stock_movements');
        $this->addVariantColumn('purchase_items');
        $this->addVariantColumn('sale_items');

        // Backfill default variants for products that have none
        $products = DB::table('products')
            ->whereNotIn('id', DB::table('product_variants')->select('product_id'))
            ->get();

        foreach ($products as $product) {
            $variantId = DB::table('product_variants')->insertGetId([
                'product_id' => $product->id,
                'name' => null,
                'short_code' => $product->short_code,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'purchase_unit_id' => $product->purchase_unit_id,
                'sale_unit_id' => $product->sale_unit_id,
                'conversion_rate' => $product->conversion_rate,
                'sale_price' => $product->sale_price,
                'cost_per_unit' => $product->cost_per_unit,
                'is_active' => $product->is_active,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (['branch_stocks', 'product_locations', 'stock_movements', 'purchase_items', 'sale_items'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'variant_id')) {
                    DB::table($table)->where('product_id', $product->id)->whereNull('variant_id')->update(['variant_id' => $variantId]);
                }
            }
        }

        $this->rebuildStockLocationUniques();
    }

    protected function addVariantColumn(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'variant_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            $onDelete = in_array($table, ['branch_stocks', 'product_locations'], true) ? 'cascade' : 'set null';
            $blueprint->foreignId('variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->{$onDelete === 'cascade' ? 'cascadeOnDelete' : 'nullOnDelete'}();
        });
    }

    protected function rebuildStockLocationUniques(): void
    {
        foreach (['branch_stocks', 'product_locations'] as $table) {
            $this->dropForeignIfExists($table, "{$table}_branch_id_foreign");
            $this->dropForeignIfExists($table, "{$table}_product_id_foreign");
            $this->dropForeignIfExists($table, "{$table}_variant_id_foreign");

            $this->dropIndexIfExists($table, "{$table}_branch_id_product_id_unique");
            $this->dropIndexIfExists($table, "{$table}_branch_id_variant_id_unique");

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique(['branch_id', 'variant_id']);
                $blueprint->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
                $blueprint->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $blueprint->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            });
        }
    }

    protected function dropForeignIfExists(string $table, string $name): void
    {
        $exists = collect(DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
              AND CONSTRAINT_NAME = ?
        ", [$table, $name]))->isNotEmpty();

        if ($exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropForeign($name);
            });
        }
    }

    protected function dropIndexIfExists(string $table, string $name): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$name]))->isNotEmpty();
        if ($exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropUnique($name);
            });
        }
    }

    public function down(): void
    {
        // Irreversible safely for production-like data; leave columns for rollback simplicity.
    }
};
