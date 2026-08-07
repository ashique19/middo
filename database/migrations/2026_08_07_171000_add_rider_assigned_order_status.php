<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY order_status ENUM(
            'pending',
            'processing',
            'ready',
            'rider_assigned',
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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::table('orders')
            ->where('order_status', 'rider_assigned')
            ->update(['order_status' => 'ready']);

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
};
