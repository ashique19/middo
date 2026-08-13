<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('kitchen_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->string('payout_channel', 20)->default('cash');
            $table->json('payout_details')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('kitchen_ledger_entry_id')->nullable()->constrained('kitchen_account_ledger')->nullOnDelete();
            $table->foreignId('middo_cash_ledger_entry_id')->nullable()->constrained('middo_cash_ledger')->nullOnDelete();
            $table->foreignId('middo_bank_account_id')->nullable()->constrained('middo_bank_accounts')->nullOnDelete();
            $table->foreignId('middo_bank_ledger_entry_id')->nullable()->constrained('middo_bank_ledger')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'kitchen_user_id']);
        });

        Schema::create('kitchen_settlement_batch_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kitchen_settlement_batch_id');
            $table->foreign('kitchen_settlement_batch_id', 'ksbi_batch_id_fk')
                ->references('id')
                ->on('kitchen_settlement_batches')
                ->cascadeOnDelete();
            $table->foreignId('partner_payable_id')->constrained('partner_payables')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->unique('partner_payable_id');
            $table->index('kitchen_settlement_batch_id', 'ksbi_batch_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_settlement_batch_items');
        Schema::dropIfExists('kitchen_settlement_batches');
    }
};
