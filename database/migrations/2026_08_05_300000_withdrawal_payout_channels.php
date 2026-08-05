<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['kitchen_withdrawal_requests', 'rider_withdrawal_requests'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'payout_channel')) {
                    $table->string('payout_channel', 20)->default('cash')->after('notes');
                }
                if (! Schema::hasColumn($tableName, 'payout_details')) {
                    $table->json('payout_details')->nullable()->after('payout_channel');
                }
                if (! Schema::hasColumn($tableName, 'attachment_path')) {
                    $table->string('attachment_path')->nullable()->after('review_notes');
                }
                if (! Schema::hasColumn($tableName, 'middo_bank_account_id')) {
                    $table->foreignId('middo_bank_account_id')->nullable()->after('middo_cash_ledger_entry_id')
                        ->constrained('middo_bank_accounts')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'middo_bank_ledger_entry_id')) {
                    $table->foreignId('middo_bank_ledger_entry_id')->nullable()->after('middo_bank_account_id')
                        ->constrained('middo_bank_ledger')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['kitchen_withdrawal_requests', 'rider_withdrawal_requests'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'middo_bank_ledger_entry_id')) {
                    $table->dropConstrainedForeignId('middo_bank_ledger_entry_id');
                }
                if (Schema::hasColumn($tableName, 'middo_bank_account_id')) {
                    $table->dropConstrainedForeignId('middo_bank_account_id');
                }
                foreach (['attachment_path', 'payout_details', 'payout_channel'] as $col) {
                    if (Schema::hasColumn($tableName, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
