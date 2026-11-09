<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('product_limit')->nullable();
            $table->unsignedInteger('user_limit')->nullable();
            $table->unsignedInteger('branch_limit')->nullable();
            $table->boolean('ads_enabled')->default(false);
            $table->decimal('monthly_price', 12, 2)->default(0);
            $table->decimal('quarterly_price', 12, 2)->default(0);
            $table->decimal('yearly_price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('plans')->insert([
            [
                'code' => 'free',
                'name' => 'Free',
                'description' => 'One person, one branch, and 10 products.',
                'product_limit' => 10,
                'user_limit' => 1,
                'branch_limit' => 1,
                'ads_enabled' => true,
                'monthly_price' => 0,
                'quarterly_price' => 0,
                'yearly_price' => 0,
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'One branch, 75 products, and 3 users.',
                'product_limit' => 75,
                'user_limit' => 3,
                'branch_limit' => 1,
                'ads_enabled' => false,
                'monthly_price' => 0,
                'quarterly_price' => 0,
                'yearly_price' => 0,
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'premium',
                'name' => 'Premium',
                'description' => 'Two branches, 10 users, and no product cap. Raise the limits on a company for a custom deal.',
                'product_limit' => null,
                'user_limit' => 10,
                'branch_limit' => 2,
                'ads_enabled' => false,
                'monthly_price' => 0,
                'quarterly_price' => 0,
                'yearly_price' => 0,
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('plan')->constrained('plans')->nullOnDelete();
            $table->string('billing_interval')->default('monthly')->after('monthly_fee');
            $table->boolean('override_limits')->default(false)->after('billing_interval');
            $table->unsignedInteger('product_limit')->nullable()->after('override_limits');
            $table->unsignedInteger('user_limit')->nullable()->after('product_limit');
            $table->unsignedInteger('branch_limit')->nullable()->after('user_limit');
        });

        $planIds = DB::table('plans')->pluck('id', 'code');
        foreach ($planIds as $code => $id) {
            DB::table('tenants')->where('plan', $code)->update(['plan_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn([
                'billing_interval',
                'override_limits',
                'product_limit',
                'user_limit',
                'branch_limit',
            ]);
        });

        Schema::dropIfExists('plans');
    }
};
