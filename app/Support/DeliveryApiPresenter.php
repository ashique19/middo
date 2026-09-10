<?php

namespace App\Support;

use App\Models\Area;
use App\Models\CashHandover;
use App\Models\City;
use App\Models\CustomRun;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\StaffAlert;
use App\Models\User;
use Carbon\Carbon;

class DeliveryApiPresenter
{
    public static function user(User $user): array
    {
        $user->loadMissing('role');

        $areaName = $user->area_id
            ? Area::query()->whereKey($user->area_id)->value('name')
            : ($user->getAttributes()['area'] ?? null);
        $cityName = $user->city_id
            ? City::query()->whereKey($user->city_id)->value('name')
            : ($user->getAttributes()['city'] ?? null);

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'balance' => (int) $user->balance,
            'address' => $user->address,
            'area' => $areaName,
            'city' => $cityName,
            'area_id' => $user->area_id,
            'city_id' => $user->city_id,
            'role' => $user->role?->name,
            'rider_shift_status' => $user->riderShiftStatus(),
            'can_accept_new_runs' => $user->canAcceptNewRuns(),
            'preferred_payout_channel' => $user->preferredPayoutChannel(),
            'has_complete_payout_method' => $user->hasCompletePayoutMethod(
                $user->preferredPayoutChannel()
            ),
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
            'run_id' => $alert->meta['order_id'] ?? $alert->meta['run_id'] ?? null,
            'read_at' => $alert->read_at?->toIso8601String(),
            'is_unread' => $alert->read_at === null,
            'created_at' => $alert->created_at?->toIso8601String(),
        ];
    }

    /**
     * Rider-facing lunch run: includes party PII + street address.
     */
    public static function run(Order $order, User $rider): array
    {
        $riderId = (int) $rider->id;
        $order->loadMissing(['menuItem', 'user', 'area', 'deliveryRider', 'orderGroup.kitchen', 'middoBoxes']);
        $kitchen = $order->orderGroup?->kitchen;
        $party = $order->partyPayload();
        $commission = RiderCommission::forLunchOrder($rider, $order);
        $mine = $order->isAssignedToRider($riderId);
        $cashDue = max(0, (int) ($party['amount_due'] ?? $order->amountDue()));

        return [
            'id' => $order->id,
            'type' => 'lunch',
            'status' => $order->order_status,
            'status_label' => str($order->order_status)->replace('_', ' ')->title()->toString(),
            'label' => self::runLabel($order, $kitchen?->name),
            'menu_name' => $order->menuItem?->name ?? 'Order',
            'menu_item_id' => $order->menu_item_id,
            'quantity' => (int) $order->quantity,
            'delivery_date' => $order->delivery_date?->toDateString(),
            'delivery_time' => $order->delivery_time,
            'date_label' => self::dateLabel($order->delivery_date?->toDateString()),
            'kitchen_name' => $kitchen?->name ?? 'Kitchen',
            'kitchen_mobile' => $kitchen?->mobile,
            'kitchen_address' => $kitchen?->address,
            'area_name' => $order->area?->name ?? $order->orderGroup?->area?->name,
            'customer_name' => $party['customer_name'] ?? null,
            'account_holder_name' => $party['account_holder_name'] ?? null,
            'receiver_name' => $party['receiver_name'] ?? null,
            'receiver_phone' => $party['receiver_mobile'] ?? null,
            'receiver_mobile' => $party['receiver_mobile'] ?? null,
            'has_separate_receiver' => (bool) ($party['has_separate_receiver'] ?? false),
            'address' => $order->address,
            'amount_due' => $cashDue,
            'amount_paid' => $party['amount_paid'] ?? $order->amountPaidValue(),
            'cash_due' => $cashDue,
            'commission_amount' => $commission,
            'show_commission' => RiderCommission::shouldShow($commission),
            'payment_status' => $order->payment_status,
            'payment_method' => $party['payment_method'] ?? null,
            'payment_method_label' => $party['payment_method_label'] ?? null,
            'box_codes' => $order->middoBoxes->pluck('qr_code_id')->values()->all(),
            'can_pick_up' => $mine && $order->isAwaitingRiderPickup(),
            'can_pickup' => $mine && $order->isAwaitingRiderPickup(),
            'can_mark_delivered' => $mine && $order->isOnTheWayToDelivery(),
            'can_deliver' => $mine && $order->isOnTheWayToDelivery(),
            'awaiting_kitchen_pack' => $mine && $order->isAssignedAwaitingKitchenPrep(),
            'awaiting_accept' => false,
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    public static function deliveredOrder(Order $order): array
    {
        $order->loadMissing(['menuItem', 'user', 'area']);
        $party = $order->partyPayload();
        $cashDue = $order->amountDue();
        $cashCollected = (int) ($order->cash_collected ?? 0);

        return [
            'id' => $order->id,
            'menu_name' => $order->menuItem?->name ?? 'Order',
            'receiver_name' => $party['receiver_name'] ?? $party['customer_name'] ?? null,
            'receiver_phone' => $party['receiver_mobile'] ?? null,
            'customer_name' => $party['customer_name'] ?? null,
            'address' => $order->address,
            'area_name' => $order->area?->name,
            'quantity' => (int) $order->quantity,
            'delivered_at' => $order->updated_at?->toIso8601String(),
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'amount_due' => $cashDue,
            'cash_due' => $cashDue,
            'cash_collected' => $cashCollected > 0 || $order->isPaid(),
            'cash_collected_amount' => $cashCollected,
            'due_to_middo' => $order->dueToMiddoAmount(),
            'can_collect_cash' => $order->isDelivered() && ! $order->isPaid() && $cashDue > 0,
        ];
    }

    public static function historyRun(Order $order): array
    {
        $order->loadMissing(['menuItem', 'area', 'orderGroup.kitchen']);

        return [
            'id' => $order->id,
            'type' => 'lunch',
            'label' => self::runLabel($order, $order->orderGroup?->kitchen?->name),
            'status' => $order->order_status,
            'delivered_at' => $order->updated_at?->toIso8601String(),
            'menu_name' => $order->menuItem?->name,
            'quantity' => (int) $order->quantity,
            'area_name' => $order->area?->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    public static function box(MiddoBox $box, array $flags = []): array
    {
        return array_merge([
            'id' => $box->id,
            'qr_code_id' => $box->qr_code_id,
            'box_model_type' => $box->box_model_type,
            'asset_status' => $box->asset_status,
            'held_by_user_id' => $box->held_by_user_id,
            'kitchen_id' => $box->kitchen_id,
            'pickup_rider_id' => $box->pickup_rider_id,
        ], $flags);
    }

    public static function handover(CashHandover $handover): array
    {
        $handover->loadMissing(['items.order.menuItem']);

        return [
            'id' => $handover->id,
            'amount' => (int) $handover->amount,
            'target' => $handover->target,
            'status' => $handover->status,
            'notes' => $handover->notes,
            'created_at' => $handover->created_at?->toIso8601String(),
            'accepted_at' => $handover->accepted_at?->toIso8601String(),
            'rejection_proposed_at' => $handover->rejection_proposed_at?->toIso8601String(),
            'order_ids' => $handover->items->pluck('order_id')->values()->all(),
            'items' => $handover->items->map(fn ($item) => [
                'order_id' => $item->order_id,
                'amount' => (int) $item->amount,
                'menu_name' => $item->order?->menuItem?->name,
            ])->values()->all(),
        ];
    }

    public static function customRun(CustomRun $run): array
    {
        $run->loadMissing(['area', 'rider']);

        return [
            'id' => $run->id,
            'title' => $run->label(),
            'from_label' => $run->from_label,
            'to_label' => $run->to_label,
            'status' => $run->status,
            'notes' => $run->notes,
            'commission_amount' => (int) $run->commission_amount,
            'area_name' => $run->area?->name,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'can_start' => $run->isPending(),
            'can_complete' => $run->isStarted(),
        ];
    }

    public static function account(User $rider): array
    {
        $riderId = (int) $rider->id;
        $due = (int) $rider->balance;
        $wallet = RiderAccountLedger::balance($riderId);

        return [
            'balance' => $wallet,
            'cash_on_hand' => $due,
            'due_to_middo' => $due,
            'receivable' => max(0, $wallet),
            'wallet' => $wallet,
            'can_request_payment' => $wallet > 0 && $due === 0,
            'preferred_payout_channel' => $rider->preferredPayoutChannel(),
            'has_complete_payout_method' => $rider->hasCompletePayoutMethod(
                $rider->preferredPayoutChannel()
            ),
        ];
    }

    public static function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    protected static function runLabel(Order $order, ?string $kitchenName): string
    {
        $area = $order->area?->name ?? $order->orderGroup?->area?->name ?? 'Area';
        $kitchen = $kitchenName ?: 'Kitchen';

        return 'Lunch · '.$kitchen.' → '.$area;
    }

    protected static function dateLabel(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $today = now('Asia/Dhaka')->toDateString();
        $tomorrow = now('Asia/Dhaka')->copy()->addDay()->toDateString();

        if ($date === $today) {
            return 'Today';
        }

        if ($date === $tomorrow) {
            return 'Tomorrow';
        }

        return Carbon::parse($date, 'Asia/Dhaka')->format('l, F-j');
    }
}
