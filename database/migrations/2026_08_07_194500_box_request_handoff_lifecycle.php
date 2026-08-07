<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_box_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('kitchen_box_requests', 'allocated_qty')) {
                $table->unsignedInteger('allocated_qty')->default(0)->after('quantity');
            }
            if (! Schema::hasColumn('kitchen_box_requests', 'closed_note')) {
                $table->text('closed_note')->nullable()->after('note');
            }
            if (! Schema::hasColumn('kitchen_box_requests', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('kitchen_box_requests', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('closed_by');
            }
        });

        // Migrate legacy fulfilled → closed for clarity
        if (Schema::hasTable('kitchen_box_requests')) {
            DB::table('kitchen_box_requests')
                ->where('status', 'fulfilled')
                ->update(['status' => 'closed', 'closed_at' => DB::raw('COALESCE(reviewed_at, updated_at, CURRENT_TIMESTAMP)')]);
        }

        if (! Schema::hasTable('kitchen_box_request_boxes')) {
            Schema::create('kitchen_box_request_boxes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kitchen_box_request_id')->constrained('kitchen_box_requests')->cascadeOnDelete();
                $table->foreignId('middo_box_id')->constrained('middo_boxes')->restrictOnDelete();
                $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 32)->default('ready_for_pickup');
                $table->timestamps();

                $table->unique('middo_box_id');
                $table->index(['kitchen_box_request_id', 'status']);
                $table->index(['rider_id', 'status']);
            });
        }

        if (! Schema::hasTable('kitchen_box_request_logs')) {
            Schema::create('kitchen_box_request_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kitchen_box_request_id')->constrained('kitchen_box_requests')->cascadeOnDelete();
                $table->string('event', 64);
                $table->text('note')->nullable();
                $table->json('meta')->nullable();
                $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['kitchen_box_request_id', 'id']);
            });
        }

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
            'ops_acked_warehouse_return',
            'retired_at_warehouse',
            'reactivated_at_warehouse',
            'staged_for_kitchen_pickup',
            'rider_accepted_kitchen_stock',
            'handed_to_kitchen_stock'
        ) NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_box_request_logs');
        Schema::dropIfExists('kitchen_box_request_boxes');

        Schema::table('kitchen_box_requests', function (Blueprint $table) {
            if (Schema::hasColumn('kitchen_box_requests', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
            if (Schema::hasColumn('kitchen_box_requests', 'closed_by')) {
                $table->dropConstrainedForeignId('closed_by');
            }
            if (Schema::hasColumn('kitchen_box_requests', 'closed_note')) {
                $table->dropColumn('closed_note');
            }
            if (Schema::hasColumn('kitchen_box_requests', 'allocated_qty')) {
                $table->dropColumn('allocated_qty');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::table('middo_box_logs')
            ->whereIn('log_action', [
                'staged_for_kitchen_pickup',
                'rider_accepted_kitchen_stock',
                'handed_to_kitchen_stock',
            ])
            ->update(['log_action' => 'dispatched_to_kitchen']);

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
            'ops_acked_warehouse_return',
            'retired_at_warehouse',
            'reactivated_at_warehouse'
        ) NOT NULL");
    }
};
