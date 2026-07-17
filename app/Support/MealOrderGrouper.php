<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MealOrderGrouper
{
    /**
     * Attach a newly created order into an open area+menu+date group (or create one).
     */
    public function assignOrder(Order $order, ?int $actorId = null): OrderGroup
    {
        $areaId = $order->area_id ?: $order->user?->area_id;

        if (! $areaId) {
            throw new \InvalidArgumentException('Order area is required for auto-grouping.');
        }

        $maxQuantity = (int) config('middo.auto_meal_group_quantity', 10);
        $actorId = $actorId ?: (int) ($order->created_by ?: $order->user_id);
        $deliveryDate = $order->delivery_date;
        $deliveryDateKey = $deliveryDate->format('Ymd');
        $menuId = (int) $order->menu_item_id;

        return DB::transaction(function () use ($order, $areaId, $maxQuantity, $actorId, $deliveryDate, $deliveryDateKey, $menuId) {
            if (OrderGroupOrder::query()->where('order_id', $order->id)->exists()) {
                return $order->orderGroup()->firstOrFail();
            }

            $openGroups = OrderGroup::query()
                ->where('menu_id', $menuId)
                ->whereDate('delivery_date', $deliveryDate->toDateString())
                ->where('area_id', $areaId)
                ->whereNull('kitchen_id')
                ->withSum('orders', 'quantity')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($openGroups as $group) {
                $currentQty = (int) ($group->orders_sum_quantity ?? 0);
                if ($currentQty + (int) $order->quantity <= $maxQuantity) {
                    $group->orders()->attach($order->id);

                    return $group->fresh();
                }
            }

            $sequence = $this->nextSequence($menuId, $deliveryDateKey, (int) $areaId);

            $group = OrderGroup::create([
                'name' => sprintf('GRP-%s-A%d-M%d-%03d', $deliveryDateKey, $areaId, $menuId, $sequence),
                'menu_id' => $menuId,
                'area_id' => $areaId,
                'delivery_date' => $deliveryDate,
                'kitchen_id' => null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $group->orders()->attach($order->id);

            return $group;
        });
    }

    public function autoGroup(Collection $orders, int $userId): int
    {
        $ungrouped = $orders->reject(function (Order $order) {
            return OrderGroupOrder::query()->where('order_id', $order->id)->exists();
        });

        $created = 0;

        foreach ($ungrouped as $order) {
            $order->loadMissing('user');
            if (! ($order->area_id || $order->user?->area_id)) {
                continue;
            }
            $this->assignOrder($order, $userId);
            $created++;
        }

        return $created;
    }

    protected function nextSequence(int $menuId, string $deliveryDate, int $areaId): int
    {
        $prefix = "GRP-{$deliveryDate}-A{$areaId}-M{$menuId}-";

        $latest = OrderGroup::query()
            ->where('name', 'like', $prefix.'%')
            ->orderByDesc('name')
            ->value('name');

        if (! $latest) {
            return 1;
        }

        $suffix = (int) substr($latest, strlen($prefix));

        return $suffix + 1;
    }
}
