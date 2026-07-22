<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 40)->default('other'); // delivery | handling | packaging | other
            $table->string('description')->nullable();
            $table->unsignedInteger('amount'); // BDT (integer taka)
            $table->string('calculation', 20)->default('per_delivery'); // per_delivery | per_item | per_checkout
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('applies_to', 20)->default('both'); // orders | packages | both
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'applies_to']);
            $table->index(['area_id', 'menu_item_id']);
        });

        Schema::create('order_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('charge_id')->nullable()->constrained('charges')->nullOnDelete();
            $table->string('name');
            $table->string('category', 40);
            $table->string('calculation', 20);
            $table->unsignedInteger('unit_amount');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->index(['order_id']);
        });

        Schema::create('package_subscription_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_subscription_id')->constrained('package_subscriptions')->cascadeOnDelete();
            $table->foreignId('charge_id')->nullable()->constrained('charges')->nullOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('name');
            $table->string('category', 40);
            $table->string('calculation', 20);
            $table->unsignedInteger('unit_amount');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->index(['package_subscription_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('charges_amount')->default(0)->after('total_amount');
        });

        Schema::table('package_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('charges_amount')->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('package_subscriptions', function (Blueprint $table) {
            $table->dropColumn('charges_amount');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('charges_amount');
        });

        Schema::dropIfExists('package_subscription_charges');
        Schema::dropIfExists('order_charges');
        Schema::dropIfExists('charges');
    }
};
