<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'meal.accept_window_starts_at'],
            ['value' => '10:00']
        );
    }

    public function down(): void
    {
        Setting::query()->where('key', 'meal.accept_window_starts_at')->delete();
    }
};
