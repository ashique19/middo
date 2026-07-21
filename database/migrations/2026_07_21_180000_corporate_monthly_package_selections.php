<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_subscriptions', function (Blueprint $table) {
            $table->string('schedule_status')->default('scheduled')->after('status');
            // awaiting_schedule | scheduled
            $table->string('target_month', 7)->nullable()->after('end_date'); // Y-m
        });

        Schema::create('package_subscription_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_subscription_id')
                ->constrained('package_subscriptions')
                ->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->unsignedSmallInteger('day_count');
            $table->timestamps();

            $table->unique(['package_subscription_id', 'menu_item_id'], 'pkg_sub_sel_unique');
        });

        // Existing subscriptions already have delivery orders — treat as scheduled.
        if (Schema::hasTable('package_subscriptions')) {
            DB::table('package_subscriptions')->update(['schedule_status' => 'scheduled']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('package_subscription_selections');

        Schema::table('package_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['schedule_status', 'target_month']);
        });
    }
};
