<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('menu_items', 'kitchen_commission')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('kitchen_commission', 8, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('menu_items', 'kitchen_commission')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('kitchen_commission');
        });
    }
};
