<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('middo_box_logs', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('log_action');
            $table->foreignId('performed_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
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
            'returned_damaged_to_warehouse'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::table('middo_box_logs')
                ->whereIn('log_action', ['marked_damaged_at_kitchen', 'returned_damaged_to_warehouse'])
                ->update(['log_action' => 'returned_to_warehouse']);

            DB::statement("ALTER TABLE middo_box_logs MODIFY log_action ENUM(
                'dispatched_to_kitchen',
                'received_at_kitchen',
                'picked_by_delivery_from_kitchen',
                'delivered_to_corporate',
                'picked_from_corporate_by_delivery',
                'returned_to_kitchen',
                'returned_to_warehouse',
                'registered_at_warehouse'
            ) NOT NULL");
        }

        Schema::table('middo_box_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('performed_by');
            $table->dropColumn('notes');
        });
    }
};
