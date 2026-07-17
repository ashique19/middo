<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // topup | debit | refund
            $table->integer('amount'); // always positive
            $table->integer('balance_after');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->string('gateway_token')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('middo_boxes', function (Blueprint $table) {
            $table->boolean('ready_for_pickup')->default(false)->after('asset_status');
            $table->timestamp('ready_for_pickup_at')->nullable()->after('ready_for_pickup');
        });
    }

    public function down(): void
    {
        Schema::table('middo_boxes', function (Blueprint $table) {
            $table->dropColumn(['ready_for_pickup', 'ready_for_pickup_at']);
        });

        Schema::dropIfExists('wallet_transactions');
    }
};
