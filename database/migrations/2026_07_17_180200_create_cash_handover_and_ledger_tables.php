<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('middo_cash_ledger', function (Blueprint $table) {
            $table->id();
            $table->integer('amount'); // positive = credit to Middo, negative = debit
            $table->integer('balance_after');
            $table->string('entry_type', 64); // cash_handover_accepted, adjustment, ...
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('entry_type');
        });

        Schema::create('cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('status', 32)->default('pending'); // pending|accepted|rejected
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index('status');
        });

        Schema::create('cash_handover_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_handover_id')->constrained('cash_handovers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->unique('order_id');
            $table->index('cash_handover_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_handover_orders');
        Schema::dropIfExists('cash_handovers');
        Schema::dropIfExists('middo_cash_ledger');
    }
};
