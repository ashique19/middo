<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'vat_rate_pct')) {
                $table->decimal('vat_rate_pct', 5, 2)->default(0)->after('food_amount');
            }
            if (! Schema::hasColumn('orders', 'vat_amount')) {
                $table->unsignedInteger('vat_amount')->default(0)->after('vat_rate_pct');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'vat_amount')) {
                $table->dropColumn('vat_amount');
            }
            if (Schema::hasColumn('orders', 'vat_rate_pct')) {
                $table->dropColumn('vat_rate_pct');
            }
        });
    }
};
