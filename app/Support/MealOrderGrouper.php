<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MealOrderGrouper
{
    public function autoGroup(Collection $orders, int $userId): int
    {
        $maxQuantity = config('middo.auto_meal_group_quantity', 10);
        $groupedOrderIds = OrderGroupOrder::query()->pluck('order_id');
        $ungrouped = $orders->reject(fn (Order $order) => $groupedOrderIds->contains($order->id));

        if ($ungrouped->isEmpty()) {
            return 0;
        }

        $created = 0;

        DB::transaction(function () use ($ungrouped, $maxQuantity, $userId, &$created) {
            $buckets = $ungrouped->groupBy(fn (Order $order) => $order->menu_item_id.'|'.$order->delivery_date->toDateString());

            foreach ($buckets as $bucketOrders) {
                $menuId = (int) $bucketOrders->first()->menu_item_id;
                $deliveryDate = $bucketOrders->first()->delivery_date;
                $deliveryDateKey = $deliveryDate->format('Ymd');
                $sequence = $this->nextSequence($menuId, $deliveryDateKey);

                $currentBatch = collect();
                $currentQuantity = 0;

                foreach ($bucketOrders->sortBy('id') as $order) {
                    if ($currentQuantity > 0 && ($currentQuantity + $order->quantity) > $maxQuantity) {
                        $created += $this->persistBatch($currentBatch, $menuId, $deliveryDate, $deliveryDateKey, $sequence++, $userId);
                        $currentBatch = collect();
                        $currentQuantity = 0;
                    }

                    $currentBatch->push($order);
                    $currentQuantity += $order->quantity;

                    if ($currentQuantity >= $maxQuantity) {
                        $created += $this->persistBatch($currentBatch, $menuId, $deliveryDate, $deliveryDateKey, $sequence++, $userId);
                        $currentBatch = collect();
                        $currentQuantity = 0;
                    }
                }

                if ($currentBatch->isNotEmpty()) {
                    $created += $this->persistBatch($currentBatch, $menuId, $deliveryDate, $deliveryDateKey, $sequence, $userId);
                }
            }
        });

        return $created;
    }

    protected function nextSequence(int $menuId, string $deliveryDate): int
    {
        $prefix = "GRP-{$deliveryDate}-M{$menuId}-";

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

    protected function persistBatch(
        Collection $orders,
        int $menuId,
        $deliveryDate,
        string $deliveryDateKey,
        int $sequence,
        int $userId
    ): int {
        if ($orders->isEmpty()) {
            return 0;
        }

        $group = OrderGroup::create([
            'name' => sprintf('GRP-%s-M%d-%03d', $deliveryDateKey, $menuId, $sequence),
            'menu_id' => $menuId,
            'delivery_date' => $deliveryDate,
            'kitchen_id' => null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $group->orders()->attach($orders->pluck('id'));

        return $orders->count();
    }
}
