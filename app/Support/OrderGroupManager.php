<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderGroupManager
{
    public function ungroup(int $orderId): void
    {
        $order = Order::with('orderGroup')->findOrFail($orderId);
        $this->detachOrder($order);
    }

    public function handleDrop(int $sourceOrderId, string $targetType, ?int $targetId, ?int $userId = null): void
    {
        $userId ??= Auth::id();

        if (! $userId) {
            throw new InvalidArgumentException('User must be authenticated.');
        }

        if ($targetType === 'ungrouped') {
            $this->ungroup($sourceOrderId);

            return;
        }

        $source = Order::with('orderGroup')->findOrFail($sourceOrderId);

        if ($targetType === 'group') {
            if (! $targetId) {
                throw new InvalidArgumentException('Group id is required.');
            }

            $group = OrderGroup::findOrFail($targetId);
            $this->assertSameDeliveryDate($source, $group->delivery_date->toDateString());
            $this->addOrderToGroup($source, $group, $userId);

            return;
        }

        if ($targetType === 'order') {
            if (! $targetId || $sourceOrderId === $targetId) {
                return;
            }

            $target = Order::with('orderGroup')->findOrFail($targetId);
            $this->assertSameDeliveryDate($source, $target->delivery_date->toDateString());

            if ($target->orderGroup) {
                $this->addOrderToGroup($source, $target->orderGroup, $userId);

                return;
            }

            $this->createManualGroupFromOrders($source, $target, $userId);
        }
    }

    public function addOrderToGroup(Order $order, OrderGroup $group, int $userId): void
    {
        DB::transaction(function () use ($order, $group, $userId) {
            $this->detachOrder($order);

            OrderGroupOrder::create([
                'order_group_id' => $group->id,
                'order_id' => $order->id,
            ]);

            $group->update(['updated_by' => $userId]);
        });
    }

    public function createManualGroupFromOrders(Order $source, Order $target, int $userId): OrderGroup
    {
        return DB::transaction(function () use ($source, $target, $userId) {
            $this->detachOrder($source);
            $this->detachOrder($target);

            $deliveryDate = $source->delivery_date;
            $menuId = $source->menu_item_id;

            $group = OrderGroup::create([
                'name' => $this->nextManualGroupName($menuId, $deliveryDate->format('Ymd')),
                'menu_id' => $menuId,
                'area_id' => $source->area_id ?: $target->area_id,
                'delivery_date' => $deliveryDate,
                'kitchen_id' => null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $group->orders()->attach([$source->id, $target->id]);

            return $group;
        });
    }

    protected function detachOrder(Order $order): void
    {
        $pivot = OrderGroupOrder::where('order_id', $order->id)->first();

        if (! $pivot) {
            return;
        }

        $groupId = $pivot->order_group_id;
        $pivot->delete();

        $remaining = OrderGroupOrder::where('order_group_id', $groupId)->count();

        if ($remaining === 0) {
            OrderGroup::where('id', $groupId)->delete();
        }
    }

    protected function nextManualGroupName(int $menuId, string $deliveryDateKey): string
    {
        $prefix = "MGRP-{$deliveryDateKey}-M{$menuId}-";

        $latest = OrderGroup::query()
            ->where('name', 'like', $prefix.'%')
            ->orderByDesc('name')
            ->value('name');

        $sequence = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;

        return sprintf('%s%03d', $prefix, $sequence);
    }

    protected function assertSameDeliveryDate(Order $order, string $deliveryDate): void
    {
        if ($order->delivery_date->toDateString() !== $deliveryDate) {
            throw new InvalidArgumentException('Orders must share the same delivery date.');
        }
    }
}
