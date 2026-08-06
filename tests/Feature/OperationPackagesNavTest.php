<?php

namespace Tests\Feature;

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OperationPackagesNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_packages_nav_group_sits_after_orders(): void
    {
        foreach (['admin', 'corporate', 'kitchen', 'delivery', 'operation', 'accounts', 'ground_marketing'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        Artisan::call('db:seed', ['--class' => 'NavSeeder']);

        $roleId = Role::query()->where('name', 'operation')->value('id');
        $this->assertNotNull($roleId);

        $orders = Nav::query()
            ->where('role_id', $roleId)
            ->where('title', 'Orders')
            ->whereNull('parent_id')
            ->first();
        $packages = Nav::query()
            ->where('role_id', $roleId)
            ->where('title', 'Packages')
            ->whereNull('parent_id')
            ->whereNull('route_name')
            ->first();

        $this->assertNotNull($orders);
        $this->assertNotNull($packages);
        $this->assertSame((int) $orders->order + 1, (int) $packages->order);

        $childRoutes = Nav::query()
            ->where('parent_id', $packages->id)
            ->orderBy('order')
            ->pluck('route_name')
            ->all();

        $this->assertSame([
            'operation.packages.index',
            'operation.subscriptions.index',
            'operation.packages.demand',
            'operation.packages.insights',
        ], $childRoutes);

        $this->assertFalse(
            Nav::query()
                ->where('role_id', $roleId)
                ->where('route_name', 'operation.packages.index')
                ->where('parent_id', '!=', $packages->id)
                ->exists()
        );
    }
}
