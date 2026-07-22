<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('type', 20); // percent | fixed
            $table->unsignedInteger('value');
            $table->unsignedInteger('min_subtotal')->default(0);
            $table->unsignedInteger('max_discount')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->string('applies_to', 20)->default('both'); // orders | packages | both
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('context', 20); // order | package
            $table->unsignedInteger('original_amount');
            $table->unsignedInteger('discount_amount');
            $table->unsignedInteger('final_amount');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('package_subscription_id')->nullable()->constrained('package_subscriptions')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('payment_method')->constrained('coupons')->nullOnDelete();
            $table->unsignedInteger('discount_amount')->default(0)->after('coupon_id');
        });

        Schema::table('package_subscriptions', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('payment_status')->constrained('coupons')->nullOnDelete();
            $table->unsignedInteger('discount_amount')->default(0)->after('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('package_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_amount');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_amount');
        });

        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
