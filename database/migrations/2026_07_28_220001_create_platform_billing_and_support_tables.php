<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan')->default('starter')->after('is_active');
            $table->string('billing_status')->default('trial')->after('plan'); // trial, active, past_due, cancelled
            $table->decimal('monthly_fee', 12, 2)->default(0)->after('billing_status');
            $table->date('trial_ends_at')->nullable()->after('monthly_fee');
            $table->text('billing_notes')->nullable()->after('trial_ends_at');
        });

        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('number')->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('open'); // open, paid, void
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('support_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('tenant_id');
            $table->foreignId('created_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_login_tokens');
        Schema::dropIfExists('platform_invoices');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['plan', 'billing_status', 'monthly_fee', 'trial_ends_at', 'billing_notes']);
        });
    }
};
