<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('max_concurrent')->default(1);
            $table->unsignedInteger('default_duration_minutes')->default(60);
            $table->boolean('public_enabled')->default(true);
            $table->boolean('allow_staff_overbook')->default(false);
            $table->string('sales_ui', 20)->default('orders');
            $table->json('business_hours')->nullable();
            $table->string('google_calendar_id')->nullable();
            $table->text('google_access_token')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->timestamp('google_token_expires_at')->nullable();
            $table->timestamps();
        });

        DB::table('booking_settings')->insert([
            'id' => 1,
            'business_hours' => json_encode(collect([
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
            ])->mapWithKeys(fn (string $day) => [$day => [
                'enabled' => $day !== 'sunday',
                'open' => '09:00',
                'close' => '17:00',
            ]])->all(), JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('booking_product_settings', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('is_bookable')->default(false);
            $table->string('price_mode', 30)->default('fixed');
            $table->boolean('requires_address')->default(false);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 60);
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('service_name');
            $table->string('price_mode', 30)->default('fixed');
            $table->decimal('price', 16, 4)->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->dateTime('scheduled_at');
            $table->dateTime('ends_at');
            $table->string('status', 30)->default('pending');
            $table->string('source', 20)->default('staff');
            $table->string('google_event_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['scheduled_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_product_settings');
        Schema::dropIfExists('booking_settings');
    }
};
