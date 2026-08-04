<?php

namespace App\Support;

use App\Models\CustomRun;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OpsRiderBoard
{
    /**
     * @return array{
     *   riders:int,
     *   awaiting:int,
     *   on_the_way:int,
     *   box_custody:int,
     *   custom_started:int
     * }
     */
    public static function counts(): array
    {
        return [
            'riders' => self::riders()->count(),
            'awaiting' => self::awaitingAccept()->count(),
            'on_the_way' => self::onTheWay()->count(),
            'box_custody' => self::boxCustody()->count(),
            'custom_started' => CustomRun::query()->where('status', CustomRun::STATUS_STARTED)->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function riders(): Collection
    {
        $deliveryRoleId = Role::query()->where('name', 'delivery')->value('id');
        if (! $deliveryRoleId) {
            return collect();
        }

        return User::query()
            ->with(['areas'])
            ->where('role_id', $deliveryRoleId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (User $rider) {
                $onWay = Order::query()
                    ->where('delivery_rider_id', $rider->id)
                    ->where('order_status', 'on_the_way_to_delivery')
                    ->count();
                $boxes = Schema::hasTable('middo_boxes')
                    ? MiddoBox::query()
                        ->where('held_by_user_id', $rider->id)
                        ->where(function ($q) {
                            $q->whereNull('kitchen_id')
                                ->orWhereColumn('kitchen_id', '!=', 'held_by_user_id');
                        })
                        ->where('asset_status', '!=', 'damaged')
                        ->count()
                    : 0;
                $customStarted = CustomRun::query()
                    ->where('rider_user_id', $rider->id)
                    ->where('status', CustomRun::STATUS_STARTED)
                    ->count();

                return [
                    'id' => $rider->id,
                    'name' => $rider->name,
                    'mobile' => $rider->mobile,
                    'areas' => $rider->areas->pluck('name')->filter()->values()->all(),
                    'due_float' => (int) $rider->balance,
                    'wallet' => RiderAccountLedger::balance((int) $rider->id),
                    'on_the_way' => $onWay,
                    'boxes' => $boxes,
                    'custom_started' => $customStarted,
                    'active_total' => $onWay + $boxes + $customStarted,
                ];
            })
            ->values();
    }

    /**
     * Packed kitchen-dispatched orders with no rider yet.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function awaitingAccept(): Collection
    {
        return Order::query()
            ->with(['menuItem', 'orderGroup.kitchen', 'user', 'area'])
            ->where('order_status', 'packed')
            ->whereNotNull('dispatched_at')
            ->whereNull('delivery_rider_id')
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->limit(100)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'menu' => $order->menuItem?->name ?? '—',
                'qty' => (int) $order->quantity,
                'delivery_date' => $order->delivery_date?->toDateString(),
                'delivery_time' => $order->delivery_time,
                'kitchen' => $order->orderGroup?->kitchenDisplayName() ?? '—',
                'group_name' => $order->orderGroup?->name,
                'area' => $order->area?->name ?? $order->orderGroup?->area?->name ?? '—',
                'corporate' => $order->user?->company_name
                    ?: trim(($order->user?->first_name ?? '').' '.($order->user?->last_name ?? '')),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function onTheWay(): Collection
    {
        return Order::query()
            ->with(['menuItem', 'deliveryRider', 'orderGroup.kitchen', 'user'])
            ->where('order_status', 'on_the_way_to_delivery')
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->limit(100)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'menu' => $order->menuItem?->name ?? '—',
                'qty' => (int) $order->quantity,
                'delivery_date' => $order->delivery_date?->toDateString(),
                'delivery_time' => $order->delivery_time,
                'rider_id' => $order->delivery_rider_id,
                'rider' => $order->deliveryRider?->name ?? '—',
                'kitchen' => $order->orderGroup?->kitchenDisplayName() ?? '—',
                'corporate' => $order->user?->company_name
                    ?: trim(($order->user?->first_name ?? '').' '.($order->user?->last_name ?? '')),
            ])
            ->values();
    }

    /**
     * Boxes currently held by a delivery rider (in transit / incoming kitchen).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function boxCustody(): Collection
    {
        if (! Schema::hasTable('middo_boxes')) {
            return collect();
        }

        $deliveryRoleId = Role::query()->where('name', 'delivery')->value('id');

        return MiddoBox::query()
            ->with(['heldByUser'])
            ->whereNotNull('held_by_user_id')
            ->where('asset_status', '!=', 'damaged')
            ->whereHas('heldByUser', function ($q) use ($deliveryRoleId) {
                if ($deliveryRoleId) {
                    $q->where('role_id', $deliveryRoleId);
                }
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (MiddoBox $box) => [
                'id' => $box->id,
                'code' => $box->qr_code_id ?? ('#'.$box->id),
                'rider_id' => $box->held_by_user_id,
                'rider' => $box->heldByUser?->name ?? '—',
                'location' => $box->locationLabel(),
                'asset_status' => $box->asset_status,
                'kitchen_id' => $box->kitchen_id,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function customRunsActive(): Collection
    {
        return CustomRun::query()
            ->with(['rider', 'area'])
            ->whereIn('status', [CustomRun::STATUS_PENDING, CustomRun::STATUS_STARTED])
            ->orderByRaw("CASE WHEN status = 'started' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (CustomRun $run) => [
                'id' => $run->id,
                'label' => $run->label(),
                'status' => $run->status,
                'area' => $run->area?->name ?? '—',
                'rider_id' => $run->rider_user_id,
                'rider' => $run->rider?->name ?? 'Open pool',
                'commission' => (int) $run->commission_amount,
                'is_pending' => $run->isPending(),
                'is_started' => $run->isStarted(),
            ])
            ->values();
    }
}
