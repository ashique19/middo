<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kitchen_warehouse_handoffs')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver !== 'sqlite') {
                try {
                    Schema::table('kitchen_warehouse_handoffs', function (Blueprint $table) {
                        $table->dropForeign(['rider_id']);
                    });
                } catch (\Throwable) {
                    // FK name may vary.
                }

                DB::statement('ALTER TABLE `kitchen_warehouse_handoffs` MODIFY `rider_id` BIGINT UNSIGNED NULL');

                Schema::table('kitchen_warehouse_handoffs', function (Blueprint $table) {
                    $table->foreign('rider_id')->references('id')->on('users')->nullOnDelete();
                });
            }

            DB::table('kitchen_warehouse_handoffs')->where('status', 'ready_for_pickup')->update(['status' => 'dispatched']);
            DB::table('kitchen_warehouse_handoffs')->where('status', 'rider_accepted')->update(['status' => 'in_transit']);
            DB::table('kitchen_warehouse_handoffs')->where('status', 'delivered')->update(['status' => 'handed_to_ops']);
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
            'rider_accepted_warehouse_return',
            'warehouse_run_requested',
            'rider_claimed_warehouse_run',
            'kitchen_dispatched_warehouse_run',
            'handed_to_ops_warehouse'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (Schema::hasTable('kitchen_warehouse_handoffs')) {
            DB::table('kitchen_warehouse_handoffs')->where('status', 'run_requested')->delete();
            DB::table('kitchen_warehouse_handoffs')->where('status', 'run_claimed')->update(['status' => 'ready_for_pickup']);
            DB::table('kitchen_warehouse_handoffs')->where('status', 'dispatched')->update(['status' => 'ready_for_pickup']);
            DB::table('kitchen_warehouse_handoffs')->where('status', 'in_transit')->update(['status' => 'rider_accepted']);
            DB::table('kitchen_warehouse_handoffs')->whereIn('status', ['handed_to_ops', 'received'])->update(['status' => 'delivered']);
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::table('middo_box_logs')
            ->whereIn('log_action', [
                'warehouse_run_requested',
                'rider_claimed_warehouse_run',
                'kitchen_dispatched_warehouse_run',
                'handed_to_ops_warehouse',
            ])
            ->update(['log_action' => 'staged_for_warehouse_pickup']);

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
};
