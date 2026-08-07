<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $default = max(1, (int) config('middo.max_order_qty_allowed', 5));

        Setting::query()->updateOrCreate(
            ['key' => 'order.max_order_qty_allowed'],
            ['value' => (string) $default]
        );

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'max_order_qty_allowed')) {
                $table->unsignedInteger('max_order_qty_allowed')->nullable()->after('balance');
            }
        });
    }

    public function down(): void
    {
        Setting::query()->where('key', 'order.max_order_qty_allowed')->delete();

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'max_order_qty_allowed')) {
                $table->dropColumn('max_order_qty_allowed');
            }
        });
    }
};
