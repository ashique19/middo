<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'rider_shift_status')) {
                $table->string('rider_shift_status', 16)->default('on')->after('status');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'original_delivery_rider_id')) {
                $table->foreignId('original_delivery_rider_id')
                    ->nullable()
                    ->after('delivery_rider_id')
                    ->constrained('users')
                    ->nullOnDelete();
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
            'ops_mid_run_reassigned'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::table('middo_box_logs')
                ->where('log_action', 'ops_mid_run_reassigned')
                ->update(['log_action' => 'picked_by_delivery_from_kitchen']);

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
                'ops_released_rider_returned_to_kitchen'
            ) NOT NULL");
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'original_delivery_rider_id')) {
                $table->dropConstrainedForeignId('original_delivery_rider_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'rider_shift_status')) {
                $table->dropColumn('rider_shift_status');
            }
        });
    }
};
