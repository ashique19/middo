<?php

namespace App\Support;

use App\Models\StaffAlert;
use App\Models\User;

class OperationApiPresenter
{
    public static function user(User $user): array
    {
        $user->loadMissing('role');

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'role' => $user->role?->name,
            'status' => $user->status,
        ];
    }

    public static function dashboardTile(string $key, string $label, int $count): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
        ];
    }

    public static function alert(StaffAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'title' => $alert->title,
            'body' => $alert->body,
            'order_group_id' => $alert->order_group_id,
            'meta' => $alert->meta ?? [],
            'read_at' => $alert->read_at?->toIso8601String(),
            'is_unread' => $alert->read_at === null,
            'created_at' => $alert->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<\App\Models\StaffAlert>  $paginator
     * @return array<string, mixed>
     */
    public static function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }


    public static function box(\App\Models\MiddoBox $box): array
    {
        return DeliveryApiPresenter::box($box);
    }

    public static function boxRequest(\App\Models\KitchenBoxRequest $request): array
    {
        $request->loadMissing([
            'kitchen:id,first_name,last_name,mobile',
            'requestedBy:id,first_name,last_name',
            'requestBoxes.rider:id,first_name,last_name',
            'requestBoxes.box:id,qr_code_id',
        ]);

        return [
            'id' => $request->id,
            'kitchen_id' => $request->kitchen_id,
            'kitchen_name' => $request->kitchen?->name,
            'quantity' => (int) $request->quantity,
            'allocated_qty' => (int) ($request->allocated_qty ?? 0),
            'status' => $request->status,
            'note' => $request->note,
            'requested_by' => $request->requestedBy?->name,
            'created_at' => $request->created_at?->toIso8601String(),
            'boxes' => $request->requestBoxes->map(fn ($link) => [
                'id' => $link->id,
                'box_id' => $link->middo_box_id ?? $link->box_id ?? null,
                'qr_code_id' => $link->box?->qr_code_id,
                'status' => $link->status,
                'rider_id' => $link->rider_id,
                'rider_name' => $link->rider?->name,
            ])->values()->all(),
        ];
    }

    public static function handover(\App\Models\CashHandover $handover): array
    {
        $payload = DeliveryApiPresenter::handover($handover);
        $handover->loadMissing('rider:id,first_name,last_name,mobile');
        $payload['rider'] = $handover->rider ? [
            'id' => $handover->rider->id,
            'name' => $handover->rider->name,
            'mobile' => $handover->rider->mobile,
        ] : null;

        return $payload;
    }

    public static function orderGroup(\App\Models\OrderGroup $group): array
    {
        $group->loadMissing(['kitchen:id,first_name,last_name,mobile', 'menuItem:id,name', 'orders']);

        return [
            'id' => $group->id,
            'name' => $group->name,
            'kitchen_id' => $group->kitchen_id,
            'kitchen_name' => $group->kitchen?->name,
            'menu_name' => $group->menuItem?->name,
            'orders_count' => $group->orders->count(),
            'qty' => (int) $group->orders->sum('quantity'),
        ];
    }

    public static function orderSummary(\App\Models\Order $order): array
    {
        $order->loadMissing([
            'menuItem:id,name',
            'user:id,first_name,last_name,mobile,company_name',
            'deliveryRider:id,first_name,last_name,mobile',
            'area:id,name',
        ]);

        return [
            'id' => $order->id,
            'order_status' => $order->order_status,
            'delivery_date' => $order->delivery_date?->toDateString() ?? $order->delivery_date,
            'delivery_time' => $order->delivery_time,
            'quantity' => (int) $order->quantity,
            'menu_name' => $order->menuItem?->name,
            'customer_name' => $order->user?->company_name ?: $order->user?->name,
            'customer_mobile' => $order->user?->mobile,
            'area_name' => $order->area?->name,
            'rider_id' => $order->delivery_rider_id,
            'rider_name' => $order->deliveryRider?->name,
            'address' => $order->address,
        ];
    }

    public static function orderDetail(\App\Models\Order $order): array
    {
        $order->loadMissing([
            'menuItem',
            'user:id,first_name,last_name,mobile,company_name',
            'deliveryRider:id,first_name,last_name,mobile',
            'orderGroup.kitchen:id,first_name,last_name,mobile',
            'area:id,name',
            'packageSubscription.package:id,name',
        ]);

        return array_merge(self::orderSummary($order), [
            'kitchen_id' => $order->orderGroup?->kitchen_id,
            'kitchen_name' => $order->orderGroup?->kitchen?->name,
            'group_id' => $order->orderGroup?->id,
            'group_name' => $order->orderGroup?->name,
            'package_name' => $order->packageSubscription?->package?->name,
            'amount_paid' => (int) ($order->amount_paid ?? 0),
            'cash_collected' => (int) ($order->cash_collected ?? 0),
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'notes' => $order->notes ?? $order->special_instructions ?? null,
        ]);
    }

    public static function complaint(\App\Models\OrderComplaint $complaint, bool $withThread = false): array
    {
        $complaint->loadMissing([
            'order.menuItem:id,name',
            'order.user:id,first_name,last_name,mobile,company_name',
        ]);

        $payload = [
            'id' => $complaint->id,
            'order_id' => $complaint->order_id,
            'status' => $complaint->status,
            'category' => $complaint->category,
            'message' => $complaint->message,
            'is_reply' => (bool) $complaint->is_reply,
            'parent_id' => $complaint->parent_id,
            'created_at' => $complaint->created_at?->toIso8601String(),
            'order' => $complaint->order ? self::orderSummary($complaint->order) : null,
        ];

        if ($withThread) {
            $payload['thread'] = $complaint->threadMessages()->map(fn ($row) => [
                'id' => $row->id,
                'message' => $row->message,
                'is_reply' => (bool) $row->is_reply,
                'created_by' => $row->createdBy?->name,
                'created_at' => $row->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $payload;
    }
}

