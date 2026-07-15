<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('dispatched_at')->nullable()->after('payment_status');
            $table->foreignId('delivery_rider_id')
                ->nullable()
                ->after('dispatched_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('order_middo_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('middo_box_id')->constrained('middo_boxes')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'middo_box_id']);
            $table->index('middo_box_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_middo_boxes');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_rider_id');
            $table->dropColumn('dispatched_at');
        });
    }
};
