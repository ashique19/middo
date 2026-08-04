<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('cash_due_to_middo')->nullable()->after('cash_collected');
        });

        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->string('target', 20)->default('kitchen')->after('amount');
            $table->index(['target', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->dropIndex(['target', 'status']);
            $table->dropColumn('target');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cash_due_to_middo');
        });
    }
};
