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
        Schema::create('staff_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreignId('order_group_id')->nullable()->constrained('order_groups')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->string('dedupe_key', 120)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'id']);
            $table->index(['type', 'order_group_id']);
            $table->unique(['user_id', 'dedupe_key']);
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'meal.accept_window_warn_minutes'],
            [
                'value' => '15',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->addNavs();
    }

    protected function addNavs(): void
    {
        $kitchenRoleId = Role::query()->where('name', 'kitchen')->value('id');
        if ($kitchenRoleId) {
            $exists = Nav::query()
                ->where('role_id', $kitchenRoleId)
                ->where('route_name', 'kitchen.alerts')
                ->exists();
            if (! $exists) {
                Nav::query()
                    ->where('role_id', $kitchenRoleId)
                    ->whereNull('parent_id')
                    ->where('order', '>=', 2)
                    ->increment('order');

                Nav::create([
                    'title' => 'Alerts',
                    'route_name' => 'kitchen.alerts',
                    'order' => 2,
                    'role_id' => $kitchenRoleId,
                ]);
            }
        }

        foreach (['admin', 'operation'] as $roleName) {
            $roleId = Role::query()->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            $route = $roleName === 'admin' ? 'admin.alerts.index' : 'operation.alerts.index';
            $exists = Nav::query()
                ->where('role_id', $roleId)
                ->where('route_name', $route)
                ->exists();
            if ($exists) {
                continue;
            }
            $max = (int) Nav::query()->where('role_id', $roleId)->whereNull('parent_id')->max('order');
            Nav::create([
                'title' => 'Alerts',
                'route_name' => $route,
                'icon' => '🔔',
                'order' => $max + 1,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        Nav::query()->whereIn('route_name', [
            'kitchen.alerts',
            'admin.alerts.index',
            'operation.alerts.index',
        ])->delete();

        DB::table('settings')->where('key', 'meal.accept_window_warn_minutes')->delete();
        Schema::dropIfExists('staff_alerts');
    }
};
