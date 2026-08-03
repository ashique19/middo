<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_subscription_selections', function (Blueprint $table) {
            if (! Schema::hasColumn('package_subscription_selections', 'unit_price')) {
                $table->unsignedInteger('unit_price')->default(0)->after('day_count');
            }
        });

        // Backfill unit_price from current menu prices for existing rows.
        if (Schema::hasTable('menu_items') && Schema::hasColumn('package_subscription_selections', 'unit_price')) {
            DB::table('package_subscription_selections')
                ->orderBy('id')
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        $price = (int) DB::table('menu_items')->where('id', $row->menu_item_id)->value('price');
                        DB::table('package_subscription_selections')
                            ->where('id', $row->id)
                            ->update(['unit_price' => max(0, $price)]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('package_subscription_selections', function (Blueprint $table) {
            if (Schema::hasColumn('package_subscription_selections', 'unit_price')) {
                $table->dropColumn('unit_price');
            }
        });
    }
};
