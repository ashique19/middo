<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('kitchen_tier', 20)->nullable()->after('status');
            $table->unsignedInteger('allowed_open_groups')->nullable()->after('kitchen_tier');
        });

        $defaults = [
            'meal.accept_window_minutes' => '120',
            'meal.auto_group_quantity' => (string) config('middo.auto_meal_group_quantity', 10),
            'kitchen.tier_defaults.silver.allowed_open_groups' => '1',
            'kitchen.tier_defaults.gold.allowed_open_groups' => '2',
            'kitchen.tier_defaults.platinum.allowed_open_groups' => '3',
        ];

        $now = now();
        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $kitchenRoleId = Role::query()->where('name', 'kitchen')->value('id');
        if ($kitchenRoleId) {
            DB::table('users')
                ->where('role_id', $kitchenRoleId)
                ->whereNull('kitchen_tier')
                ->update(['kitchen_tier' => 'silver']);

            DB::table('users')
                ->where('role_id', $kitchenRoleId)
                ->where('status', 'active')
                ->whereNull('allowed_open_groups')
                ->update(['allowed_open_groups' => 1]);
        }

        $adminId = Role::query()->where('name', 'admin')->value('id');
        if ($adminId) {
            $exists = Nav::query()
                ->where('role_id', $adminId)
                ->where('route_name', 'admin.settings.index')
                ->exists();

            if (! $exists) {
                $maxOrder = (int) Nav::query()->where('role_id', $adminId)->whereNull('parent_id')->max('order');
                Nav::create([
                    'title' => 'Settings',
                    'route_name' => 'admin.settings.index',
                    'icon' => '⚙️',
                    'order' => $maxOrder + 1,
                    'role_id' => $adminId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $adminId = Role::query()->where('name', 'admin')->value('id');
        if ($adminId) {
            Nav::query()
                ->where('role_id', $adminId)
                ->where('route_name', 'admin.settings.index')
                ->delete();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kitchen_tier', 'allowed_open_groups']);
        });

        Schema::dropIfExists('settings');
    }
};
