<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'area_id')) {
                $table->foreignId('area_id')->nullable()->after('address')->constrained()->nullOnDelete();
            }
        });

        Schema::table('order_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('order_groups', 'area_id')) {
                $table->foreignId('area_id')->nullable()->after('menu_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'area_id')) {
                $table->dropConstrainedForeignId('area_id');
            }
        });

        Schema::table('order_groups', function (Blueprint $table) {
            if (Schema::hasColumn('order_groups', 'area_id')) {
                $table->dropConstrainedForeignId('area_id');
            }
        });
    }
};
