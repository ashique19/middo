<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kitchen_warehouse_handoffs')) {
            Schema::create('kitchen_warehouse_handoffs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('middo_box_id')->constrained('middo_boxes')->restrictOnDelete();
                $table->foreignId('kitchen_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('rider_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('status', 32)->default('run_requested');
                $table->timestamps();

                $table->unique('middo_box_id');
                $table->index(['rider_id', 'status']);
                $table->index(['kitchen_id', 'status']);
            });
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE middo_box_logs MODIFY log_action ENUM(
            'dispatched_to_kitchen',
            'dispatched_to_warehouse',
            'received_at_kitchen',
            'picked_by_delivery_from_kitchen',
            'delivered_to_corporate',
            'picked_from_corporate_by_delivery',
            'returned_to_kitchen',
            'returned_to_warehouse',
            'registered_at_warehouse',
            'marked_damaged_at_kitchen',
            'returned_damaged_to_warehouse',
            'ops_released_rider_returned_to_kitchen',
            'ops_mid_run_reassigned',
            'ops_acked_warehouse_return',
            'retired_at_warehouse',
            'reactivated_at_warehouse',
            'staged_for_kitchen_pickup',
            'rider_accepted_kitchen_stock',
            'handed_to_kitchen_stock',
            'staged_for_warehouse_pickup',
            'rider_accepted_warehouse_return'
        ) NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_warehouse_handoffs');

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::table('middo_box_logs')
            ->whereIn('log_action', [
                'staged_for_warehouse_pickup',
                'rider_accepted_warehouse_return',
            ])
            ->update(['log_action' => 'dispatched_to_warehouse']);

        DB::statement("ALTER TABLE middo_box_logs MODIFY log_action ENUM(
            'dispatched_to_kitchen',
            'dispatched_to_warehouse',
            'received_at_kitchen',
            'picked_by_delivery_from_kitchen',
            'delivered_to_corporate',
            'picked_from_corporate_by_delivery',
            'returned_to_kitchen',
            'returned_to_warehouse',
            'registered_at_warehouse',
            'marked_damaged_at_kitchen',
            'returned_damaged_to_warehouse',
            'ops_released_rider_returned_to_kitchen',
            'ops_mid_run_reassigned',
            'ops_acked_warehouse_return',
            'retired_at_warehouse',
            'reactivated_at_warehouse',
            'staged_for_kitchen_pickup',
            'rider_accepted_kitchen_stock',
            'handed_to_kitchen_stock'
        ) NOT NULL");
    }
};
