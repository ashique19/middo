<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('middo_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('bank_name');
            $table->string('account_number')->nullable();
            $table->string('branch')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('middo_bank_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('middo_bank_account_id')->constrained('middo_bank_accounts')->cascadeOnDelete();
            $table->integer('amount'); // signed: credit +, debit -
            $table->integer('balance_after');
            $table->string('entry_type', 40);
            $table->string('sub_gateway', 40)->nullable();
            $table->unsignedInteger('gross_amount')->nullable();
            $table->unsignedInteger('fee_amount')->default(0);
            $table->string('gateway_token')->nullable()->unique();
            $table->string('merchant_transaction_id')->nullable();
            $table->nullableMorphs('reference');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['middo_bank_account_id', 'id']);
            $table->index('entry_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('middo_bank_ledger');
        Schema::dropIfExists('middo_bank_accounts');
    }
};
