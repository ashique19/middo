<?php

namespace App\Support;

use App\Models\CashHandover;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CodDueRecon
{
    /**
     * Day × rider COD / Due reconciliation for Middo target path.
     *
     * @return array{
     *   date: string,
     *   rows: list<array<string,mixed>>,
     *   totals: array<string,int>,
     *   short_orders: list<array<string,mixed>>,
     *   rider_float_total: int,
     *   middo_cash: int
     * }
     */
    public static function forDate(string $date): array
    {
        $orders = Order::query()
            ->with(['deliveryRider', 'menuItem', 'user'])
            ->whereDate('delivery_date', $date)
            ->whereNotNull('delivery_rider_id')
            ->where(function ($q) {
                $q->where('cash_collected', '>', 0)
                    ->orWhereNotNull('cash_due_to_middo');
            })
            ->get();

        $byRider = [];
        $shortOrders = [];

        foreach ($orders as $order) {
            $riderId = (int) $order->delivery_rider_id;
            if (! isset($byRider[$riderId])) {
                $byRider[$riderId] = [
                    'rider_id' => $riderId,
                    'rider_name' => $order->deliveryRider?->name ?? 'Rider #'.$riderId,
                    'rider_mobile' => $order->deliveryRider?->mobile,
                    'order_count' => 0,
                    'collected' => 0,
                    'commission' => 0,
                    'due' => 0,
                    'open_due' => 0,
                    'short_count' => 0,
                    'shortfall' => 0,
                ];
            }
            $byRider[$riderId]['order_count']++;
            $byRider[$riderId]['collected'] += $order->cashCollectedAmount();
            $byRider[$riderId]['commission'] += $order->commissionRetainedFromCashAmount();
            $snapshotDue = $order->cash_due_to_middo !== null
                ? max(0, (int) $order->cash_due_to_middo)
                : $order->cashCollectedAmount();
            $byRider[$riderId]['due'] += $snapshotDue;
            $byRider[$riderId]['open_due'] += $order->dueToMiddoAmount();

            $shortfall = $order->customerCashShortfallAmount();
            if ($shortfall > 0) {
                $byRider[$riderId]['short_count']++;
                $byRider[$riderId]['shortfall'] += $shortfall;
                $shortOrders[] = [
                    'id' => $order->id,
                    'rider_id' => $riderId,
                    'rider_name' => $order->deliveryRider?->name ?? 'Rider #'.$riderId,
                    'menu' => $order->menuItem?->name ?? '—',
                    'corporate' => $order->user?->company_name
                        ?: trim(($order->user?->first_name ?? '').' '.($order->user?->last_name ?? '')),
                    'bill' => $order->netTotalAmount(),
                    'paid' => $order->amountPaidValue(),
                    'collected' => $order->cashCollectedAmount(),
                    'shortfall' => $shortfall,
                    'due_to_middo' => $order->dueToMiddoAmount(),
                    'status' => $order->order_status,
                ];
            }
        }

        $handoverRows = collect();
        if (Schema::hasTable('cash_handovers') && Schema::hasTable('cash_handover_orders')) {
            $handoverRows = DB::table('cash_handover_orders as cho')
                ->join('cash_handovers as ch', 'ch.id', '=', 'cho.cash_handover_id')
                ->join('orders as o', 'o.id', '=', 'cho.order_id')
                ->whereDate('o.delivery_date', $date)
                ->where('ch.target', CashHandover::TARGET_MIDDO)
                ->select([
                    'ch.rider_id',
                    'ch.status',
                    DB::raw('SUM(cho.amount) as amount'),
                ])
                ->groupBy('ch.rider_id', 'ch.status')
                ->get();
        }

        foreach ($handoverRows as $row) {
            $riderId = (int) $row->rider_id;
            if (! isset($byRider[$riderId])) {
                $rider = User::query()->find($riderId);
                $byRider[$riderId] = [
                    'rider_id' => $riderId,
                    'rider_name' => $rider?->name ?? 'Rider #'.$riderId,
                    'rider_mobile' => $rider?->mobile,
                    'order_count' => 0,
                    'collected' => 0,
                    'commission' => 0,
                    'due' => 0,
                    'open_due' => 0,
                    'short_count' => 0,
                    'shortfall' => 0,
                ];
            }
            $byRider[$riderId]['handed'] = ($byRider[$riderId]['handed'] ?? 0) + (int) $row->amount;
            if ($row->status === 'accepted') {
                $byRider[$riderId]['accepted'] = ($byRider[$riderId]['accepted'] ?? 0) + (int) $row->amount;
            } elseif ($row->status === 'pending') {
                $byRider[$riderId]['pending_handover'] = ($byRider[$riderId]['pending_handover'] ?? 0) + (int) $row->amount;
            } elseif ($row->status === 'rejected') {
                $byRider[$riderId]['rejected_handover'] = ($byRider[$riderId]['rejected_handover'] ?? 0) + (int) $row->amount;
            }
        }

        $rows = collect($byRider)
            ->map(function (array $row) {
                $row['handed'] = (int) ($row['handed'] ?? 0);
                $row['accepted'] = (int) ($row['accepted'] ?? 0);
                $row['pending_handover'] = (int) ($row['pending_handover'] ?? 0);
                $row['rejected_handover'] = (int) ($row['rejected_handover'] ?? 0);
                $row['short_count'] = (int) ($row['short_count'] ?? 0);
                $row['shortfall'] = (int) ($row['shortfall'] ?? 0);
                $row['variance'] = $row['due'] - $row['accepted'] - $row['pending_handover'];

                return $row;
            })
            ->sortBy('rider_name')
            ->values()
            ->all();

        usort($shortOrders, fn (array $a, array $b) => $b['shortfall'] <=> $a['shortfall']);

        $totals = [
            'order_count' => (int) collect($rows)->sum('order_count'),
            'collected' => (int) collect($rows)->sum('collected'),
            'commission' => (int) collect($rows)->sum('commission'),
            'due' => (int) collect($rows)->sum('due'),
            'open_due' => (int) collect($rows)->sum('open_due'),
            'handed' => (int) collect($rows)->sum('handed'),
            'accepted' => (int) collect($rows)->sum('accepted'),
            'pending_handover' => (int) collect($rows)->sum('pending_handover'),
            'variance' => (int) collect($rows)->sum('variance'),
            'short_count' => count($shortOrders),
            'shortfall' => (int) collect($shortOrders)->sum('shortfall'),
        ];

        return [
            'date' => $date,
            'rows' => $rows,
            'totals' => $totals,
            'short_orders' => $shortOrders,
            'rider_float_total' => self::riderFloatTotal(),
            'middo_cash' => MiddoCashLedger::balance(),
        ];
    }

    /**
     * Open short-collect orders (any delivery date) for Accounts Hub.
     *
     * @return list<array<string,mixed>>
     */
    public static function openShortCollections(int $limit = 12): array
    {
        return Order::query()
            ->with(['deliveryRider', 'menuItem', 'user'])
            ->where('cash_collected', '>', 0)
            ->where('payment_status', '!=', 'paid')
            ->where('order_status', '!=', 'delivered_and_paid')
            ->orderByDesc('id')
            ->limit(max($limit * 3, 36))
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'rider' => $order->deliveryRider?->name ?? '—',
                'menu' => $order->menuItem?->name ?? '—',
                'corporate' => $order->user?->company_name
                    ?: trim(($order->user?->first_name ?? '').' '.($order->user?->last_name ?? '')),
                'collected' => $order->cashCollectedAmount(),
                'shortfall' => $order->customerCashShortfallAmount(),
                'delivery_date' => $order->delivery_date?->toDateString(),
            ])
            ->filter(fn (array $row) => $row['shortfall'] > 0)
            ->take($limit)
            ->values()
            ->all();
    }

    public static function riderFloatTotal(): int
    {
        $deliveryRoleId = Role::query()->where('name', 'delivery')->value('id');
        if (! $deliveryRoleId) {
            return 0;
        }

        return (int) User::query()->where('role_id', $deliveryRoleId)->sum('balance');
    }
}
