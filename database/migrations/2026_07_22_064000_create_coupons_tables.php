<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
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
        }

        if (! Schema::hasTable('coupon_redemptions')) {
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
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'coupon_id')) {
                $after = Schema::hasColumn('orders', 'payment_method') ? 'payment_method' : null;
                $col = $table->foreignId('coupon_id')->nullable();
                if ($after) {
                    $col->after($after);
                }
                $col->constrained('coupons')->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'discount_amount')) {
                $after = Schema::hasColumn('orders', 'coupon_id')
                    ? 'coupon_id'
                    : (Schema::hasColumn('orders', 'payment_method') ? 'payment_method' : null);
                $col = $table->unsignedInteger('discount_amount')->default(0);
                if ($after) {
                    $col->after($after);
                }
            }
        });

        Schema::table('package_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('package_subscriptions', 'coupon_id')) {
                $after = Schema::hasColumn('package_subscriptions', 'payment_status') ? 'payment_status' : null;
                $col = $table->foreignId('coupon_id')->nullable();
                if ($after) {
                    $col->after($after);
                }
                $col->constrained('coupons')->nullOnDelete();
            }

            if (! Schema::hasColumn('package_subscriptions', 'discount_amount')) {
                $after = Schema::hasColumn('package_subscriptions', 'coupon_id')
                    ? 'coupon_id'
                    : (Schema::hasColumn('package_subscriptions', 'payment_status') ? 'payment_status' : null);
                $col = $table->unsignedInteger('discount_amount')->default(0);
                if ($after) {
                    $col->after($after);
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('package_subscriptions')) {
            Schema::table('package_subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('package_subscriptions', 'coupon_id')) {
                    $table->dropConstrainedForeignId('coupon_id');
                }
                if (Schema::hasColumn('package_subscriptions', 'discount_amount')) {
                    $table->dropColumn('discount_amount');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'coupon_id')) {
                    $table->dropConstrainedForeignId('coupon_id');
                }
                if (Schema::hasColumn('orders', 'discount_amount')) {
                    $table->dropColumn('discount_amount');
                }
            });
        }

        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
