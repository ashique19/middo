<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('receiver_name')->nullable()->after('address');
            $table->string('receiver_mobile', 20)->nullable()->after('receiver_name');
            $table->unsignedInteger('amount_paid')->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['receiver_name', 'receiver_mobile', 'amount_paid']);
        });
    }
};
