<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->foreignId('rejection_proposed_by')
                ->nullable()
                ->after('accepted_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('rejection_proposed_at')
                ->nullable()
                ->after('rejection_proposed_by');
        });
    }

    public function down(): void
    {
        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejection_proposed_by');
            $table->dropColumn('rejection_proposed_at');
        });
    }
};
