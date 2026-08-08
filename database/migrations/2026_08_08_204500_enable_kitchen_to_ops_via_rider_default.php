<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $now = now();
        $existing = DB::table('settings')->where('key', 'delivery.kitchen_to_ops_via_rider')->first();

        if ($existing) {
            // Only flip the historical default-off row; leave an explicit admin "off" alone
            // if they already saved after this feature shipped with value '0' intentionally…
            // Product ask: custody tagging must be available. Force enable.
            DB::table('settings')->where('key', 'delivery.kitchen_to_ops_via_rider')->update([
                'value' => '1',
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('settings')->insert([
            'key' => 'delivery.kitchen_to_ops_via_rider',
            'value' => '1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->where('key', 'delivery.kitchen_to_ops_via_rider')->update([
            'value' => '0',
            'updated_at' => now(),
        ]);
    }
};
