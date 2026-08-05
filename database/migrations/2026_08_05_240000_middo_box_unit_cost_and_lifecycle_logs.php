<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('middo_boxes', function (Blueprint $table) {
            if (! Schema::hasColumn('middo_boxes', 'unit_cost_bdt')) {
                $table->unsignedInteger('unit_cost_bdt')->nullable()->after('total_uses_count');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE middo_box_logs MODIFY log_action ENUM(
            'dispatched_to_kitchen',
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
            'retired_at_warehouse',
            'reactivated_at_warehouse'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::table('middo_box_logs')
                ->whereIn('log_action', ['retired_at_warehouse', 'reactivated_at_warehouse'])
                ->update(['log_action' => 'registered_at_warehouse']);

            DB::statement("ALTER TABLE middo_box_logs MODIFY log_action ENUM(
                'dispatched_to_kitchen',
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
                'ops_mid_run_reassigned'
            ) NOT NULL");
        }

        Schema::table('middo_boxes', function (Blueprint $table) {
            if (Schema::hasColumn('middo_boxes', 'unit_cost_bdt')) {
                $table->dropColumn('unit_cost_bdt');
            }
        });
    }
};
