<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('prepaid_amount')->default(0)->after('amount_paid');
            $table->unsignedInteger('cash_collected')->default(0)->after('prepaid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['prepaid_amount', 'cash_collected']);
        });
    }
};
