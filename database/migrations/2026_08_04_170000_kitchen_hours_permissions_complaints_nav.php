<?php

use App\Models\Nav;
use App\Models\Role;
use App\Support\KitchenPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'day_of_week']);
        });

        KitchenPermissions::syncKitchenRole();

        $this->addNavs();
    }

    protected function addNavs(): void
    {
        $kitchenRoleId = Role::query()->where('name', 'kitchen')->value('id');
        if (! $kitchenRoleId) {
            return;
        }

        foreach ([
            ['title' => 'Complaints', 'route_name' => 'kitchen.complaints', 'after' => 'kitchen.alerts'],
            ['title' => 'Profile', 'route_name' => 'kitchen.profile', 'after' => 'kitchen.account'],
        ] as $nav) {
            $exists = Nav::query()
                ->where('role_id', $kitchenRoleId)
                ->where('route_name', $nav['route_name'])
                ->exists();
            if ($exists) {
                continue;
            }

            $afterOrder = (int) Nav::query()
                ->where('role_id', $kitchenRoleId)
                ->where('route_name', $nav['after'])
                ->value('order');

            $insertAt = $afterOrder > 0 ? $afterOrder + 1 : (
                (int) Nav::query()->where('role_id', $kitchenRoleId)->whereNull('parent_id')->max('order') + 1
            );

            Nav::query()
                ->where('role_id', $kitchenRoleId)
                ->whereNull('parent_id')
                ->where('order', '>=', $insertAt)
                ->increment('order');

            Nav::create([
                'title' => $nav['title'],
                'route_name' => $nav['route_name'],
                'order' => $insertAt,
                'role_id' => $kitchenRoleId,
            ]);
        }
    }

    public function down(): void
    {
        Nav::query()->whereIn('route_name', [
            'kitchen.complaints',
            'kitchen.profile',
        ])->delete();

        Schema::dropIfExists('kitchen_hours');
    }
};
