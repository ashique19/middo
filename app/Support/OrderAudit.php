<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Support\Facades\Auth;

/**
 * Shared audit trail for menu orders and package day-orders.
 * Both live on the orders table and write to order_logs.
 */
class OrderAudit
{
    /**
     * Explicit audit event (grouping, kitchen forward, etc.).
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function record(Order $order, string $event, array $metadata = [], ?int $performedBy = null): OrderLog
    {
        return OrderLog::create([
            'order_id' => $order->id,
            'event' => $event,
            'metadata' => $metadata === [] ? null : $metadata,
            'performed_by' => $performedBy
                ?? Auth::id()
                ?? $order->updated_by
                ?? $order->created_by,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Order $order): array
    {
        return [
            'user_id' => (int) $order->user_id,
            'menu_item_id' => (int) $order->menu_item_id,
            'package_subscription_id' => $order->package_subscription_id
                ? (int) $order->package_subscription_id
                : null,
            'source' => $order->package_subscription_id ? 'package' : 'menu',
            'quantity' => (int) $order->quantity,
            'delivery_date' => optional($order->delivery_date)?->toDateString() ?? (string) $order->delivery_date,
            'delivery_time' => (string) ($order->delivery_time ?? ''),
            'total_amount' => (int) $order->total_amount,
            'charges_amount' => (int) ($order->charges_amount ?? 0),
            'amount_paid' => (int) ($order->amount_paid ?? 0),
            'address' => (string) ($order->address ?? ''),
            'receiver_name' => (string) ($order->receiver_name ?? ''),
            'receiver_mobile' => (string) ($order->receiver_mobile ?? ''),
            'area_id' => $order->area_id ? (int) $order->area_id : null,
            'order_status' => (string) $order->order_status,
            'payment_status' => (string) $order->payment_status,
            'payment_method' => (string) ($order->payment_method ?? ''),
            'delivery_rider_id' => $order->delivery_rider_id ? (int) $order->delivery_rider_id : null,
            'dispatched_at' => optional($order->dispatched_at)?->toIso8601String(),
            'created_by' => $order->created_by ? (int) $order->created_by : null,
            'updated_by' => $order->updated_by ? (int) $order->updated_by : null,
        ];
    }

    /**
     * @param  array<string, array{from:mixed,to:mixed}>  $diff
     */
    public static function resolveEvent(Order $order, array $diff): string
    {
        $keys = array_keys($diff);
        sort($keys);

        if (isset($diff['order_status'])) {
            $to = (string) ($diff['order_status']['to'] ?? '');
            $from = (string) ($diff['order_status']['from'] ?? '');

            if ($to === 'cancelled' && $order->package_subscription_id) {
                return 'skipped';
            }

            if ($to === 'cancelled') {
                return 'cancelled';
            }

            if ($to === 'processing' && in_array($from, ['pending', ''], true)) {
                return 'kitchen_accepted';
            }

            if ($to === 'packed' || (isset($diff['dispatched_at']) && $to === 'packed')) {
                return 'dispatched';
            }

            if ($to === 'on_the_way_to_delivery') {
                return 'out_for_delivery';
            }

            if (in_array($to, ['delivered', 'delivered_and_paid'], true)) {
                return 'delivered';
            }

            if ($keys === ['order_status']) {
                return 'order_status_changed';
            }
        }

        if (isset($diff['delivery_rider_id']) && ! isset($diff['order_status'])) {
            return 'rider_assigned';
        }

        if (isset($diff['delivery_rider_id']) && isset($diff['order_status'])) {
            $to = (string) ($diff['order_status']['to'] ?? '');
            if ($to === 'on_the_way_to_delivery') {
                return 'out_for_delivery';
            }
            if (in_array($to, ['delivered', 'delivered_and_paid'], true)) {
                return 'delivered';
            }
        }

        if ($keys === ['payment_status']) {
            return 'payment_status_changed';
        }

        if (
            array_key_exists('payment_status', $diff)
            || array_key_exists('amount_paid', $diff)
            || array_key_exists('prepaid_amount', $diff)
            || array_key_exists('cash_collected', $diff)
        ) {
            return 'payment_status_changed';
        }

        if ($keys === ['quantity', 'total_amount'] || $keys === ['quantity'] || $keys === ['total_amount']) {
            if (isset($diff['quantity'])) {
                return 'quantity_changed';
            }
        }

        if (isset($diff['menu_item_id'])) {
            return 'menu_changed';
        }

        $deliveryKeys = ['delivery_date', 'delivery_time', 'address', 'receiver_name', 'receiver_mobile', 'area_id'];
        if (count(array_intersect($keys, $deliveryKeys)) > 0 && ! isset($diff['order_status'])) {
            return 'delivery_details_changed';
        }

        if (isset($diff['dispatched_at']) && ! isset($diff['order_status'])) {
            return 'dispatched';
        }

        return 'updated';
    }

    public static function label(string $event): string
    {
        return match ($event) {
            'created' => 'Order Placed',
            'order_status_changed' => 'Status Updated',
            'payment_status_changed' => 'Payment Updated',
            'quantity_changed' => 'Quantity Updated',
            'menu_changed' => 'Menu Changed',
            'delivery_details_changed' => 'Delivery Details Updated',
            'kitchen_accepted' => 'Forwarded to Kitchen',
            'dispatched' => 'Dispatched from Kitchen',
            'rider_assigned' => 'Rider Assigned',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'skipped' => 'Package Day Skipped',
            'cancelled' => 'Order Cancelled',
            'grouped' => 'Grouped for Kitchen',
            'ungrouped' => 'Removed from Group',
            'forwarded_to_kitchen' => 'Assigned to Kitchen',
            'deleted' => 'Order Deleted',
            'ops_force_status' => 'Ops Force Action',
            'ops_intervene' => 'Ops Intervention',
            default => 'Order Updated',
        };
    }

    /**
     * @param  array<string, mixed>  $log  OrderLog array shape (event, metadata, …)
     */
    public static function description(array $log): string
    {
        $metadata = $log['metadata'] ?? [];
        $event = (string) ($log['event'] ?? '');
        $changes = is_array($metadata['changes'] ?? null) ? $metadata['changes'] : [];
        $snapshot = is_array($metadata['snapshot'] ?? null) ? $metadata['snapshot'] : [];

        $humanStatus = static fn ($value) => ucfirst(str_replace('_', ' ', (string) ($value ?? 'unknown')));

        return match ($event) {
            'created' => sprintf(
                '%s order placed for %d meal%s%s.',
                ($snapshot['source'] ?? null) === 'package' ? 'Package' : 'Menu',
                (int) ($snapshot['quantity'] ?? 1),
                ((int) ($snapshot['quantity'] ?? 1)) === 1 ? '' : 's',
                ! empty($snapshot['package_subscription_id'])
                    ? ' (package #'.$snapshot['package_subscription_id'].')'
                    : ''
            ),
            'order_status_changed', 'kitchen_accepted', 'dispatched', 'out_for_delivery', 'delivered', 'skipped', 'cancelled' => sprintf(
                'Status changed from %s to %s.',
                $humanStatus($changes['order_status']['from'] ?? null),
                $humanStatus($changes['order_status']['to'] ?? null),
            ),
            'payment_status_changed' => sprintf(
                'Payment changed from %s to %s.',
                $humanStatus($changes['payment_status']['from'] ?? null),
                $humanStatus($changes['payment_status']['to'] ?? null),
            ).(isset($changes['amount_paid']['to'])
                ? sprintf(' Amount paid is now ৳%s.', number_format((int) $changes['amount_paid']['to']))
                : ''),
            'quantity_changed' => sprintf(
                'Quantity changed from %d to %d.',
                (int) ($changes['quantity']['from'] ?? 0),
                (int) ($changes['quantity']['to'] ?? 0),
            ),
            'menu_changed' => sprintf(
                'Menu changed from #%s to #%s.',
                (string) ($changes['menu_item_id']['from'] ?? '?'),
                (string) ($changes['menu_item_id']['to'] ?? '?'),
            ),
            'delivery_details_changed' => 'Delivery date, time, or address details were updated.',
            'rider_assigned' => sprintf(
                'Delivery rider set to #%s.',
                (string) ($changes['delivery_rider_id']['to'] ?? $metadata['rider_id'] ?? '?'),
            ),
            'grouped' => sprintf(
                'Added to kitchen group %s (#%s).',
                (string) ($metadata['group_name'] ?? 'group'),
                (string) ($metadata['group_id'] ?? '?'),
            ),
            'ungrouped' => sprintf(
                'Removed from kitchen group #%s.',
                (string) ($metadata['group_id'] ?? '?'),
            ),
            'forwarded_to_kitchen' => sprintf(
                'Kitchen assignment: %s → %s.',
                (string) ($metadata['from_kitchen'] ?? 'Unassigned'),
                (string) ($metadata['to_kitchen'] ?? 'Unassigned'),
            ),
            'deleted' => 'Order was removed from the schedule.',
            'ops_force_status' => sprintf(
                'Ops force: %s → %s%s',
                $humanStatus($metadata['from'] ?? null),
                $humanStatus($metadata['to'] ?? null),
                ! empty($metadata['reason']) ? ' — '.$metadata['reason'] : '',
            ),
            'ops_intervene' => sprintf(
                'Ops intervened (%s)%s',
                str_replace('_', ' ', (string) ($metadata['action'] ?? 'action')),
                ! empty($metadata['lens']) ? ' via '.$metadata['lens'].' lens' : '',
            ),
            default => 'Order details were updated.',
        };
    }
}
