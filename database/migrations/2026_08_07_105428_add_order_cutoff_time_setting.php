<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $hour = (int) config('middo.order_cutoff_hour', 15);
        $minute = (int) config('middo.order_cutoff_minute', 28);

        Setting::query()->updateOrCreate(
            ['key' => 'order.cutoff_time'],
            ['value' => sprintf('%02d:%02d', $hour, $minute)]
        );
    }

    public function down(): void
    {
        Setting::query()->where('key', 'order.cutoff_time')->delete();
    }
};
