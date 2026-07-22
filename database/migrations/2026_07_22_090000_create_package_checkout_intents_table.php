<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_checkout_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('payment_token', 80)->unique();
            $table->string('status', 30)->default('awaiting_payment');
            // awaiting_payment | paid_awaiting_otp | completed | cancelled
            $table->foreignId('meal_package_id')->constrained('meal_packages')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('omitted_weekdays')->nullable();
            $table->string('target_month', 7);
            $table->json('selections');
            $table->unsignedInteger('amount');
            $table->string('customer_name');
            $table->string('mobile', 20);
            $table->string('address_line1');
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('delivery_window', 40)->default('12:00 PM');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('otp_last_sent_at')->nullable();
            $table->foreignId('package_subscription_id')->nullable()->constrained('package_subscriptions')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_checkout_intents');
    }
};
