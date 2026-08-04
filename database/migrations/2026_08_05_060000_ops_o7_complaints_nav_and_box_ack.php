<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite' && Schema::hasTable('middo_box_logs')) {
            DB::statement("ALTER TABLE middo_box_logs MODIFY log_action ENUM(
                'dispatched_to_kitchen',
                'received_at_kitchen',
                'picked_by_delivery_from_kitchen',
                'delivered_to_corporate',
                'picked_from_corporate_by_delivery',
                'returned_to_kitchen',
                'returned_to_warehouse',
                'registered_at_warehouse',
                'marked_damaged_at_kitchen',
                'returned_damaged_to_warehouse',
                'ops_released_rider_returned_to_kitchen',
                'ops_acked_warehouse_return'
            ) NOT NULL");
        }

        if (! Schema::hasTable('navs') || ! Schema::hasTable('roles')) {
            return;
        }

        $Nav = \App\Models\Nav::class;
        $Role = \App\Models\Role::class;

        foreach (['admin', 'operation'] as $roleName) {
            $roleId = $Role::query()->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            $route = $roleName === 'admin' ? 'admin.complaints.index' : 'operation.complaints.index';
            if ($Nav::query()->where('role_id', $roleId)->where('route_name', $route)->exists()) {
                continue;
            }
            $max = (int) $Nav::query()->where('role_id', $roleId)->whereNull('parent_id')->max('order');
            $Nav::create([
                'title' => 'Complaints',
                'route_name' => $route,
                'icon' => '💬',
                'order' => $max + 1,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('navs')) {
            DB::table('navs')->whereIn('route_name', [
                'admin.complaints.index',
                'operation.complaints.index',
            ])->delete();
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite' || ! Schema::hasTable('middo_box_logs')) {
            return;
        }

        DB::table('middo_box_logs')
            ->where('log_action', 'ops_acked_warehouse_return')
            ->update(['log_action' => 'returned_to_warehouse']);

        DB::statement("ALTER TABLE middo_box_logs MODIFY log_action ENUM(
            'dispatched_to_kitchen',
            'received_at_kitchen',
            'picked_by_delivery_from_kitchen',
            'delivered_to_corporate',
            'picked_from_corporate_by_delivery',
            'returned_to_kitchen',
            'returned_to_warehouse',
            'registered_at_warehouse',
            'marked_damaged_at_kitchen',
            'returned_damaged_to_warehouse',
            'ops_released_rider_returned_to_kitchen'
        ) NOT NULL");
    }
};
