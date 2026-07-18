<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->unsignedInteger('price_per_day');
            $table->string('diet_tag')->default('classic');
            $table->unsignedSmallInteger('duration_days')->default(30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('thumbnail')->nullable();
            $table->string('status')->default('draft'); // draft|published|archived
            $table->integer('display_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meal_package_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_package_id')->constrained('meal_packages')->cascadeOnDelete();
            $table->date('delivery_date');
            $table->foreignId('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['meal_package_id', 'delivery_date']);
        });

        Schema::create('package_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('meal_package_id')->constrained('meal_packages')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->date('start_date');
            $table->date('end_date');
            $table->json('omitted_weekdays')->nullable(); // 0=Sun … 6=Sat
            $table->unsignedInteger('billable_days')->default(0);
            $table->unsignedInteger('price_per_day');
            $table->unsignedInteger('total_amount');
            $table->unsignedInteger('amount_paid')->default(0);
            $table->string('payment_status')->default('pending'); // pending|paid|failed
            $table->string('status')->default('active'); // active|completed|cancelled
            $table->string('delivery_time')->nullable();
            $table->text('address')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_mobile')->nullable();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('package_subscription_id')
                ->nullable()
                ->after('menu_item_id')
                ->constrained('package_subscriptions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_subscription_id');
        });

        Schema::dropIfExists('package_subscriptions');
        Schema::dropIfExists('meal_package_days');
        Schema::dropIfExists('meal_packages');
    }
};
