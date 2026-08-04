<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_account_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('entry_type', 40);
            $table->nullableMorphs('reference');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rider_user_id', 'id']);
            $table->index(['entry_type']);
        });

        Schema::create('middo_operating_costs', function (Blueprint $table) {
            $table->id();
            $table->string('cost_type', 40);
            $table->unsignedInteger('amount');
            $table->string('run_type', 40)->nullable();
            $table->foreignId('rider_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->nullableMorphs('reference');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cost_type', 'id']);
            $table->index(['run_type']);
        });

        Schema::create('rider_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('rider_ledger_entry_id')->nullable()->constrained('rider_account_ledger')->nullOnDelete();
            $table->foreignId('middo_cash_ledger_entry_id')->nullable()->constrained('middo_cash_ledger')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'rider_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_withdrawal_requests');
        Schema::dropIfExists('middo_operating_costs');
        Schema::dropIfExists('rider_account_ledger');
    }
};
