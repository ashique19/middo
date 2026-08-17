<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('middo_boxes')) {
            return;
        }

        Schema::table('middo_boxes', function (Blueprint $table) {
            if (! Schema::hasColumn('middo_boxes', 'pickup_rider_id')) {
                $table->unsignedBigInteger('pickup_rider_id')->nullable()->after('held_by_user_id');
                $table->index('pickup_rider_id');
            }
            if (! Schema::hasColumn('middo_boxes', 'return_kitchen_id')) {
                $table->unsignedBigInteger('return_kitchen_id')->nullable()->after('kitchen_id');
                $table->index('return_kitchen_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('middo_boxes') || ! Schema::hasColumn('middo_boxes', 'pickup_rider_id')) {
            return;
        }

        Schema::table('middo_boxes', function (Blueprint $table) {
            if (Schema::hasColumn('middo_boxes', 'pickup_rider_id')) {
                $table->dropIndex(['pickup_rider_id']);
                $table->dropColumn('pickup_rider_id');
            }
            if (Schema::hasColumn('middo_boxes', 'return_kitchen_id')) {
                $table->dropIndex(['return_kitchen_id']);
                $table->dropColumn('return_kitchen_id');
            }
        });
    }
};
