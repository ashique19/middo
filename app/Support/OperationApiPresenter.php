<?php

namespace App\Support;

use App\Models\CashHandover;
use App\Models\KitchenBoxRequest;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\OrderGroup;
use App\Models\StaffAlert;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
     * @param  LengthAwarePaginator<StaffAlert>  $paginator
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

    public static function box(MiddoBox $box): array
    {
        return DeliveryApiPresenter::box($box);
    }

    public static function boxRequest(KitchenBoxRequest $request): array
    {
        $request->loadMissing([
            'kitchen:id,first_name,last_name,mobile,profile_photo_path',
            'requestedBy:id,first_name,last_name',
            'requestBoxes.rider:id,first_name,last_name',
            'requestBoxes.box:id,qr_code_id',
        ]);

        return [
            'id' => $request->id,
            'kitchen_id' => $request->kitchen_id,
            'kitchen_name' => $request->kitchen?->name,
            'kitchen_profile_photo_url' => $request->kitchen?->profilePhotoUrl(),
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

    public static function handover(CashHandover $handover): array
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

    public static function orderGroup(OrderGroup $group): array
    {
        $group->loadMissing([
            'kitchen:id,first_name,last_name,mobile,profile_photo_path',
            'menuItem:id,name',
            'area:id,name',
            'orders.menuItem:id,name',
            'orders.user:id,first_name,last_name,mobile,company_name',
            'orders.deliveryRider:id,first_name,last_name,mobile',
        ]);

        return [
            'id' => $group->id,
            'name' => $group->name,
            'delivery_date' => $group->delivery_date?->toDateString() ?? $group->delivery_date,
            'kitchen_id' => $group->kitchen_id,
            'kitchen_name' => $group->kitchen?->name,
            'kitchen_mobile' => $group->kitchen?->mobile,
            'kitchen_profile_photo_url' => $group->kitchen?->profilePhotoUrl(),
            'menu_name' => $group->menuItem?->name,
            'area_name' => $group->area?->name,
            'orders_count' => $group->orders->count(),
            'qty' => (int) $group->orders->sum('quantity'),
            'orders' => $group->orders
                ->sortByDesc('id')
                ->values()
                ->map(fn (Order $order) => self::orderSummary($order))
                ->all(),
        ];
    }

    /**
     * Compact party card for customer / kitchen / rider deep-links from order screens.
     *
     * @return array<string, mixed>
     */
    public static function party(User $user): array
    {
        $user->loadMissing(['role:id,name', 'area:id,name', 'city:id,name']);
        $role = $user->role?->name;

        $payload = [
            'id' => $user->id,
            'role' => $role,
            'name' => $user->company_name ?: $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'company_name' => $user->company_name,
            'mobile' => $user->mobile,
            'area_name' => $user->area?->name,
            'city_name' => $user->city?->name,
            'status' => $user->status,
            'profile_photo_url' => $user->profilePhotoUrl(),
        ];

        if ($role === 'corporate') {
            $payload['open_orders_count'] = Order::query()
                ->where('user_id', $user->id)
                ->whereNotIn('order_status', [
                    OrderTransition::DELIVERED,
                    OrderTransition::DELIVERED_AND_PAID,
                    OrderTransition::CANCELLED,
                ])
                ->count();
        }

        if ($role === 'kitchen') {
            $payload['active_groups_count'] = OrderGroup::query()
                ->where('kitchen_id', $user->id)
                ->whereDate('delivery_date', '>=', now()->toDateString())
                ->count();
        }

        if ($role === 'delivery') {
            $payload['active_runs_count'] = Order::query()
                ->where('delivery_rider_id', $user->id)
                ->whereIn('order_status', [
                    OrderTransition::RIDER_ASSIGNED,
                    OrderTransition::PACKED,
                    OrderTransition::ON_THE_WAY_TO_DELIVERY,
                ])
                ->count();
        }

        return $payload;
    }

    public static function orderSummary(Order $order): array
    {
        // Do not constrain HasOneThrough orderGroup columns — SQLite errors with
        // "ambiguous column name: id" when joining order_groups ↔ order_group_orders.
        $order->loadMissing([
            'menuItem:id,name',
            'user:id,first_name,last_name,mobile,company_name',
            'deliveryRider:id,first_name,last_name,mobile',
            'area:id,name',
            'orderGroup',
        ]);

        return [
            'id' => $order->id,
            'order_status' => $order->order_status,
            'delivery_date' => $order->delivery_date?->toDateString() ?? $order->delivery_date,
            'delivery_time' => $order->delivery_time,
            'quantity' => (int) $order->quantity,
            'menu_name' => $order->menuItem?->name,
            'customer_id' => $order->user_id,
            'customer_name' => $order->user?->company_name ?: $order->user?->name,
            'customer_mobile' => $order->user?->mobile,
            'area_name' => $order->area?->name,
            'rider_id' => $order->delivery_rider_id,
            'rider_name' => $order->deliveryRider?->name,
            'rider_mobile' => $order->deliveryRider?->mobile,
            'group_id' => $order->orderGroup?->id,
            'group_name' => $order->orderGroup?->name,
            'address' => $order->address,
            'can_release_rider' => (string) $order->order_status === OrderTransition::ON_THE_WAY_TO_DELIVERY
                && $order->delivery_rider_id !== null,
        ];
    }

    public static function orderDetail(Order $order): array
    {
        $order->loadMissing([
            'menuItem',
            'user:id,first_name,last_name,mobile,company_name',
            'deliveryRider:id,first_name,last_name,mobile',
            'orderGroup.kitchen:id,first_name,last_name,mobile,profile_photo_path',
            'area:id,name',
            'packageSubscription.package:id,name',
        ]);

        return array_merge(self::orderSummary($order), [
            'kitchen_id' => $order->orderGroup?->kitchen_id,
            'kitchen_name' => $order->orderGroup?->kitchen?->name,
            'kitchen_mobile' => $order->orderGroup?->kitchen?->mobile,
            'kitchen_profile_photo_url' => $order->orderGroup?->kitchen?->profilePhotoUrl(),
            'package_name' => $order->packageSubscription?->package?->name,
            'amount_paid' => (int) ($order->amount_paid ?? 0),
            'cash_collected' => (int) ($order->cash_collected ?? 0),
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'notes' => $order->notes ?? $order->special_instructions ?? null,
            'release_rider_hint' => 'Unassigns the rider, returns Middo boxes to the kitchen, voids the open delivery share, and sets the order back to packed so another rider can take it.',
        ]);
    }

    public static function complaint(OrderComplaint $complaint, bool $withThread = false): array
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
