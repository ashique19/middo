<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\PartnerPayable;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-level order view (O-ORDER-LENS): middo | corporate | kitchen | rider.
 */
class OrderLens
{
    public const MIDDO = 'middo';

    public const CORPORATE = 'corporate';

    public const KITCHEN = 'kitchen';

    public const RIDER = 'rider';

    /** @var list<string> */
    public const ALL = [
        self::MIDDO,
        self::CORPORATE,
        self::KITCHEN,
        self::RIDER,
    ];

    public static function normalize(?string $lens): string
    {
        $lens = strtolower(trim((string) $lens));

        return in_array($lens, self::ALL, true) ? $lens : self::MIDDO;
    }

    public static function defaultForRole(?string $role): string
    {
        return match ($role) {
            'corporate' => self::CORPORATE,
            'kitchen' => self::KITCHEN,
            'delivery' => self::RIDER,
            default => self::MIDDO,
        };
    }

    public static function isStaff(?string $role): bool
    {
        return in_array($role, ['admin', 'operation'], true);
    }

    /**
     * Lenses the actor may open for this order.
     *
     * @return list<string>
     */
    public static function allowedFor(User $actor, Order $order): array
    {
        $role = $actor->role?->name ?? $actor->loadMissing('role')->role?->name;

        if (self::isStaff($role)) {
            return self::ALL;
        }

        return match ($role) {
            'corporate' => (int) $order->user_id === (int) $actor->id
                ? [self::CORPORATE]
                : [],
            'kitchen' => (int) ($order->orderGroup?->kitchen_id) === (int) $actor->id
                ? [self::KITCHEN]
                : [],
            'delivery' => self::riderMayView($order, $actor)
                ? [self::RIDER]
                : [],
            default => [],
        };
    }

    public static function assertCanView(User $actor, Order $order, string $lens): void
    {
        $lens = self::normalize($lens);
        $allowed = self::allowedFor($actor, $order);

        if ($allowed === [] || ! in_array($lens, $allowed, true)) {
            abort(403, 'You cannot view this order through that lens.');
        }
    }

    protected static function riderMayView(Order $order, User $rider): bool
    {
        if ((int) $order->delivery_rider_id === (int) $rider->id) {
            return true;
        }

        // Packed awaiting accept — any delivery rider may open the run sheet.
        return $order->isAwaitingRiderAccept();
    }

    /**
     * @return array{
     *   lens: string,
     *   buyer: array<string,mixed>,
     *   party: array<string,mixed>,
     *   tracking: list<array<string,mixed>>,
     *   money: array<string,mixed>|null,
     *   context: array<string,mixed>,
     *   actions: array<string,bool>
     * }
     */
    public static function payload(Order $order, string $lens, ?User $actor = null): array
    {
        $lens = self::normalize($lens);
        $order->loadMissing([
            'user.role',
            'menuItem',
            'area.city',
            'orderGroup.kitchen',
            'orderGroup.menuItem',
            'orderGroup.orders.menuItem',
            'orderGroup.orders.user',
            'deliveryRider',
            'packageSubscription.package',
            'logs.performedBy',
            'moneyEvents',
            'partnerPayables.beneficiary',
            'middoBoxes',
            'complaints',
        ]);

        $party = $order->partyPayload();
        $buyer = CorporateApiPresenter::order($order);
        $tracking = $order->logs
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($log) {
                $event = CorporateApiPresenter::trackEvent($log);

                return array_merge($event, [
                    'at_label' => optional($log->created_at)?->timezone('Asia/Dhaka')->format('M d, Y g:i A'),
                ]);
            })
            ->all();

        return [
            'lens' => $lens,
            'buyer' => $buyer,
            'party' => $party,
            'tracking' => $tracking,
            'money' => self::moneyForLens($order, $lens, $actor),
            'context' => self::contextForLens($order, $lens),
            'actions' => self::actionsForLens($order, $lens, $actor),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function moneyForLens(Order $order, string $lens, ?User $actor): ?array
    {
        return match ($lens) {
            self::MIDDO => OrderMoneyFlow::treeForOrder($order),
            self::CORPORATE => [
                'amount_paid' => (int) ($order->amount_paid ?? 0),
                'amount_due' => (int) $order->amountDue(),
                'total_amount' => (int) $order->total_amount,
                'payment_method_label' => $order->paymentMethodLabel(),
                'payment_status' => $order->payment_status,
                // No Middo P&L / partner shares / Middo cash.
            ],
            self::KITCHEN => self::kitchenMoney($order),
            self::RIDER => self::riderMoney($order, $actor),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function kitchenMoney(Order $order): array
    {
        $payable = $order->partnerPayables
            ->first(fn ($p) => $p->beneficiary_role === PartnerPayable::ROLE_KITCHEN);

        return [
            'kitchen_share' => (int) ($order->kitchen_share_amount
                ?? $payable?->amount
                ?? ($order->menuItem?->kitchen_commission ?? 0) * (int) $order->quantity),
            'payable_status' => $payable?->status,
            'payable_amount' => $payable ? (int) $payable->amount : null,
            // Hide corporate wallet + Middo rest.
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function riderMoney(Order $order, ?User $actor): array
    {
        $payable = $order->partnerPayables
            ->first(fn ($p) => $p->beneficiary_role === PartnerPayable::ROLE_DELIVERY);

        $rider = $order->deliveryRider
            ?? (($actor && $actor->role?->name === 'delivery') ? $actor : null);

        $commission = $rider
            ? RiderCommission::forLunchOrder($rider, $order)
            : (int) (($order->menuItem?->delivery_commission ?? 0) * (int) $order->quantity);

        return [
            'amount_due' => (int) $order->amountDue(),
            'cash_collected' => (int) ($order->cash_collected ?? 0),
            'commission' => $commission,
            'payable_status' => $payable?->status,
            'payable_amount' => $payable ? (int) $payable->amount : null,
            'payment_method_label' => $order->paymentMethodLabel(),
            // Hide kitchen capacity + Middo rest.
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function contextForLens(Order $order, string $lens): array
    {
        $boxes = $order->middoBoxes->map(fn ($box) => [
            'id' => $box->id,
            'qr_code_id' => $box->qr_code_id,
            'held_by_user_id' => $box->held_by_user_id,
            'kitchen_id' => $box->kitchen_id,
            'asset_status' => $box->asset_status,
        ])->values()->all();

        $group = $order->orderGroup;
        $mates = [];
        if ($group && in_array($lens, [self::KITCHEN, self::MIDDO], true)) {
            $mates = $group->orders
                ->reject(fn (Order $o) => (int) $o->id === (int) $order->id)
                ->sortBy('delivery_time')
                ->values()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'quantity' => $o->quantity,
                    'order_status' => $o->order_status,
                    'delivery_time' => $o->delivery_time,
                    'menu_name' => $o->menuItem?->name ?? '—',
                    'customer_name' => $o->partyPayload()['customer_name'] ?? '—',
                ])
                ->all();
        }

        $deadline = null;
        if (in_array($lens, [self::KITCHEN, self::MIDDO], true)
            && in_array($order->order_status, ['processing', 'ready'], true)
            && $order->dispatched_at === null) {
            $at = DispatchDeadline::forOrder($order);
            $deadline = [
                'iso' => $at->toIso8601String(),
                'label' => $at->timezone('Asia/Dhaka')->format('g:i A'),
                'is_late' => $at->lt(now('Asia/Dhaka')),
            ];
        }

        $complaints = [];
        if (in_array($lens, [self::MIDDO, self::CORPORATE], true) && Schema::hasTable('order_complaints')) {
            $complaints = $order->complaints
                ->sortByDesc('id')
                ->values()
                ->map(fn (OrderComplaint $c) => [
                    'id' => $c->id,
                    'category' => $c->category,
                    'message' => $c->message,
                    'is_reply' => (bool) $c->is_reply,
                ])
                ->all();
        }

        $buyerFlags = CorporateApiPresenter::order($order);

        return [
            'group_id' => $group?->id,
            'group_name' => $group?->name,
            'kitchen_id' => $group?->kitchen_id,
            'kitchen_name' => $group?->kitchen?->name,
            'dispatched_at' => $order->dispatched_at?->timezone('Asia/Dhaka')->format('M d, Y g:i A'),
            'awaiting_rider' => $order->isAwaitingRiderAccept(),
            'boxes' => $boxes,
            'group_mates' => $mates,
            'deadline' => $deadline,
            'complaints' => $complaints,
            'can_skip' => (bool) ($buyerFlags['can_skip'] ?? false),
            'can_delete' => (bool) ($buyerFlags['can_delete'] ?? false),
        ];
    }

    /**
     * @return array<string, bool>
     */
    protected static function actionsForLens(Order $order, string $lens, ?User $actor): array
    {
        $role = $actor?->role?->name;
        $staff = self::isStaff($role);
        $isKitchen = $role === 'kitchen' && (int) ($order->orderGroup?->kitchen_id) === (int) $actor?->id;
        $buyer = CorporateApiPresenter::order($order);

        return match ($lens) {
            self::MIDDO => [
                'force_cancel' => $staff
                    && in_array($order->order_status, ['pending', OrderTransition::PROCESSING, OrderTransition::READY], true)
                    && $order->dispatched_at === null,
                'release_rider' => $staff
                    && $order->order_status === OrderTransition::ON_THE_WAY_TO_DELIVERY
                    && $order->delivery_rider_id !== null,
            ],
            self::CORPORATE => [
                'cancel_pending' => ($staff || (int) $order->user_id === (int) $actor?->id)
                    && $order->order_status === 'pending'
                    && (bool) ($buyer['can_delete'] ?? false),
            ],
            self::KITCHEN => [
                'mark_ready' => ($staff || $isKitchen)
                    && $order->order_status === OrderTransition::PROCESSING
                    && $order->dispatched_at === null,
            ],
            self::RIDER => [
                'release_rider' => $staff
                    && $order->order_status === OrderTransition::ON_THE_WAY_TO_DELIVERY
                    && $order->delivery_rider_id !== null,
            ],
            default => [],
        };
    }
}
