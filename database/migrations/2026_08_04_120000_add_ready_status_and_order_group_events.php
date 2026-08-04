<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_group_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_group_id')->constrained('order_groups')->cascadeOnDelete();
            $table->foreignId('kitchen_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_group_id', 'type']);
            $table->index(['kitchen_id', 'type', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY order_status ENUM(
            'pending',
            'processing',
            'ready',
            'packed',
            'on_the_way_to_delivery',
            'delivered',
            'delivered_and_paid',
            'cancelled',
            'others'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::table('orders')
                ->where('order_status', 'ready')
                ->update(['order_status' => 'processing']);

            DB::statement("ALTER TABLE orders MODIFY order_status ENUM(
                'pending',
                'processing',
                'packed',
                'on_the_way_to_delivery',
                'delivered',
                'delivered_and_paid',
                'cancelled',
                'others'
            ) NOT NULL DEFAULT 'pending'");
        }

        Schema::dropIfExists('order_group_events');
    }
};
