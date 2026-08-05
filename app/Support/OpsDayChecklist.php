<?php

namespace App\Support;

use App\Models\CashHandover;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Date-filtered ops checklist covering lunch / box / cash handoffs (a–h).
 */
class OpsDayChecklist
{
    /**
     * @return array{
     *   date: string,
     *   sections: list<array<string,mixed>>,
     *   totals: array<string,int>
     * }
     */
    public static function forDate(string $date): array
    {
        $sections = [
            self::sectionA($date),
            self::sectionB($date),
            self::sectionC($date),
            self::sectionD($date),
            self::sectionE($date),
            self::sectionF($date),
            self::sectionG($date),
            self::sectionH($date),
        ];

        return [
            'date' => $date,
            'sections' => $sections,
            'totals' => [
                'attention' => (int) collect($sections)->sum(fn (array $s) => (int) ($s['attention'] ?? 0)),
                'rows' => (int) collect($sections)->sum(fn (array $s) => count($s['rows'] ?? [])),
            ],
        ];
    }

    /**
     * a) Kitchen → Delivery (awaiting accept + on the way)
     *
     * @return array<string,mixed>
     */
    protected static function sectionA(string $date): array
    {
        $awaiting = Order::query()
            ->with(['menuItem', 'orderGroup.kitchen', 'area'])
            ->where('order_status', 'packed')
            ->whereNotNull('dispatched_at')
            ->whereNull('delivery_rider_id')
            ->whereDate('delivery_date', $date)
            ->orderBy('delivery_time')
            ->limit(25)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'label' => ($o->menuItem?->name ?? 'Order').' · qty '.(int) $o->quantity,
                'meta' => ($o->orderGroup?->kitchenDisplayName() ?? '—').' · '.($o->delivery_time ?? ''),
                'badge' => 'Awaiting accept',
                'tone' => 'amber',
            ]);

        $onWay = Order::query()
            ->with(['menuItem', 'deliveryRider'])
            ->where('order_status', 'on_the_way_to_delivery')
            ->whereDate('delivery_date', $date)
            ->orderBy('delivery_time')
            ->limit(25)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'label' => ($o->menuItem?->name ?? 'Order').' · qty '.(int) $o->quantity,
                'meta' => ($o->deliveryRider?->name ?? '—').' · '.($o->delivery_time ?? ''),
                'badge' => 'On the way',
                'tone' => 'sky',
            ]);

        $rows = $awaiting->concat($onWay)->values()->all();

        return self::section(
            'a',
            'Kitchen → Delivery',
            'Packed awaiting accept and on-the-way lunch for this delivery date.',
            'riders.index',
            $rows,
            [
                'awaiting' => $awaiting->count(),
                'on_the_way' => $onWay->count(),
            ],
            $awaiting->count()
        );
    }

    /**
     * b) Delivery → Customer
     *
     * @return array<string,mixed>
     */
    protected static function sectionB(string $date): array
    {
        $orders = Order::query()
            ->with(['menuItem', 'deliveryRider'])
            ->whereIn('order_status', ['delivered', 'delivered_and_paid'])
            ->whereDate('delivery_date', $date)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $rows = $orders->map(fn (Order $o) => [
            'id' => $o->id,
            'label' => ($o->menuItem?->name ?? 'Order').' · '.($o->deliveryRider?->name ?? '—'),
            'meta' => str($o->order_status)->replace('_', ' ')->headline()->toString()
                .' · paid '.(($o->payment_status === 'paid') ? 'yes' : 'no'),
            'badge' => $o->order_status === 'delivered_and_paid' ? 'Delivered+paid' : 'Delivered',
            'tone' => $o->order_status === 'delivered_and_paid' ? 'emerald' : 'amber',
        ])->all();

        $deliveredOnly = $orders->where('order_status', 'delivered')->count();

        return self::section(
            'b',
            'Delivery → Customer',
            'Orders marked delivered (or delivered and paid) for this date.',
            'orders.search',
            $rows,
            [
                'delivered' => $orders->where('order_status', 'delivered')->count(),
                'delivered_and_paid' => $orders->where('order_status', 'delivered_and_paid')->count(),
            ],
            $deliveredOnly
        );
    }

    /**
     * c) Box collect customer → delivery
     *
     * @return array<string,mixed>
     */
    protected static function sectionC(string $date): array
    {
        $ready = collect();
        if (Schema::hasTable('middo_boxes')) {
            $ready = MiddoBox::query()
                ->with('heldByUser')
                ->where('ready_for_pickup', true)
                ->when(
                    Schema::hasColumn('middo_boxes', 'ready_for_pickup_at'),
                    fn ($q) => $q->where(function ($inner) use ($date) {
                        $inner->whereDate('ready_for_pickup_at', $date)
                            ->orWhereNull('ready_for_pickup_at');
                    })
                )
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (MiddoBox $b) => [
                    'id' => $b->id,
                    'label' => $b->qr_code_id,
                    'meta' => 'Ready for pickup · held '.($b->heldByUser?->name ?? 'customer/corp'),
                    'badge' => 'Ready',
                    'tone' => 'amber',
                    'link' => 'box',
                ]);
        }

        $collected = self::boxLogRows($date, ['picked_from_corporate_by_delivery'], 20, 'Collected', 'emerald');

        $rows = $ready->concat(collect($collected))->values()->all();

        return self::section(
            'c',
            'Box: Customer → Delivery',
            'Ready-for-pickup boxes and collect events that day.',
            'riders.index',
            $rows,
            [
                'ready' => $ready->count(),
                'collected' => count($collected),
            ],
            $ready->count()
        );
    }

    /**
     * d) Box delivery → kitchen
     *
     * @return array<string,mixed>
     */
    protected static function sectionD(string $date): array
    {
        $incoming = collect();
        if (Schema::hasTable('middo_boxes')) {
            $incoming = MiddoBox::query()
                ->with(['kitchen', 'heldByUser'])
                ->whereNotNull('kitchen_id')
                ->where(function ($q) {
                    $q->whereNull('held_by_user_id')
                        ->orWhereColumn('held_by_user_id', '!=', 'kitchen_id');
                })
                ->where('asset_status', '!=', 'retired')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (MiddoBox $b) => [
                    'id' => $b->id,
                    'label' => $b->qr_code_id,
                    'meta' => 'To '.($b->kitchen?->name ?? 'kitchen').' · held '.($b->heldByUser?->name ?? 'in transit'),
                    'badge' => 'Incoming',
                    'tone' => 'amber',
                    'link' => 'box',
                ]);
        }

        $handoffs = self::boxLogRows($date, ['returned_to_kitchen', 'received_at_kitchen'], 20, 'Handoff', 'sky');

        return self::section(
            'd',
            'Box: Delivery → Kitchen',
            'Live incoming-to-kitchen custody plus handoff logs for the day.',
            'middo-boxes.index',
            $incoming->concat(collect($handoffs))->values()->all(),
            [
                'incoming' => $incoming->count(),
                'handoffs' => count($handoffs),
            ],
            $incoming->count()
        );
    }

    /**
     * e) Box kitchen → WH
     *
     * @return array<string,mixed>
     */
    protected static function sectionE(string $date): array
    {
        $openReturns = 0;
        $openRows = collect();
        if (Schema::hasTable('middo_boxes')) {
            $openReturns = OpsBoxCustody::returnsQuery()->count();
            $openRows = OpsBoxCustody::returnsQuery()
                ->with('heldByUser')
                ->orderByDesc('id')
                ->limit(15)
                ->get()
                ->map(fn (MiddoBox $b) => [
                    'id' => $b->id,
                    'label' => $b->qr_code_id,
                    'meta' => 'Awaiting ops ack · '.$b->locationLabel(),
                    'badge' => 'Inbound',
                    'tone' => 'rose',
                    'link' => 'box',
                ]);
        }

        $logs = self::boxLogRows(
            $date,
            ['returned_to_warehouse', 'returned_damaged_to_warehouse', 'ops_acked_warehouse_return'],
            20,
            'WH return',
            'violet'
        );

        return self::section(
            'e',
            'Box: Kitchen → Warehouse',
            'Inbound returns awaiting ack (live) and WH return/ack logs for the day.',
            'middo-boxes.index',
            $openRows->concat(collect($logs))->values()->all(),
            [
                'open_returns' => $openReturns,
                'day_logs' => count($logs),
            ],
            $openReturns
        );
    }

    /**
     * f) WH → kitchen
     *
     * @return array<string,mixed>
     */
    protected static function sectionF(string $date): array
    {
        $dispatched = self::boxLogRows($date, ['dispatched_to_kitchen'], 25, 'Dispatched', 'emerald');

        return self::section(
            'f',
            'Box: Warehouse → Kitchen',
            'Ops WH→kitchen dispatch events for this calendar day.',
            'middo-boxes.index',
            $dispatched,
            ['dispatched' => count($dispatched)],
            0
        );
    }

    /**
     * g) COD customer → rider
     *
     * @return array<string,mixed>
     */
    protected static function sectionG(string $date): array
    {
        $collected = Order::query()
            ->with(['deliveryRider', 'menuItem'])
            ->whereDate('delivery_date', $date)
            ->where('cash_collected', '>', 0)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $unpaid = Order::query()
            ->with(['deliveryRider', 'menuItem'])
            ->whereDate('delivery_date', $date)
            ->where('order_status', 'delivered')
            ->where('payment_status', '!=', 'paid')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $short = $collected->filter(fn (Order $o) => $o->hasCustomerCashShortfall());

        $rows = $unpaid->map(fn (Order $o) => [
            'id' => $o->id,
            'label' => ($o->menuItem?->name ?? 'Order').' · '.($o->deliveryRider?->name ?? '—'),
            'meta' => 'Due ৳'.number_format($o->amountDue()).' · no cash yet',
            'badge' => 'Unpaid',
            'tone' => 'rose',
        ])->concat($short->map(fn (Order $o) => [
            'id' => $o->id,
            'label' => ($o->menuItem?->name ?? 'Order').' · '.($o->deliveryRider?->name ?? '—'),
            'meta' => 'Collected ৳'.number_format($o->cashCollectedAmount()).' · shortfall ৳'.number_format($o->customerCashShortfallAmount()),
            'badge' => 'Short',
            'tone' => 'amber',
        ]))->concat($collected->reject(fn (Order $o) => $o->hasCustomerCashShortfall())->take(15)->map(fn (Order $o) => [
            'id' => $o->id,
            'label' => ($o->menuItem?->name ?? 'Order').' · '.($o->deliveryRider?->name ?? '—'),
            'meta' => 'Collected ৳'.number_format($o->cashCollectedAmount()).' · Due float ৳'.number_format($o->dueToMiddoAmount()),
            'badge' => 'Collected',
            'tone' => 'emerald',
        ]))->values()->all();

        return self::section(
            'g',
            'COD: Customer → Delivery',
            'Cash collected, shortfalls, and still-unpaid delivered orders.',
            'cod-recon.index',
            $rows,
            [
                'collected' => $collected->count(),
                'unpaid' => $unpaid->count(),
                'short' => $short->count(),
            ],
            $unpaid->count() + $short->count()
        );
    }

    /**
     * h) Cash rider → kitchen / Middo
     *
     * @return array<string,mixed>
     */
    protected static function sectionH(string $date): array
    {
        $rows = [];
        $kitchenPending = 0;
        $middoPending = 0;
        $kitchenAccepted = 0;
        $middoAccepted = 0;

        if (Schema::hasTable('cash_handovers') && Schema::hasTable('cash_handover_orders')) {
            $handoverIds = DB::table('cash_handover_orders as cho')
                ->join('orders as o', 'o.id', '=', 'cho.order_id')
                ->whereDate('o.delivery_date', $date)
                ->distinct()
                ->pluck('cho.cash_handover_id');

            $handovers = CashHandover::query()
                ->with('rider')
                ->whereIn('id', $handoverIds)
                ->orderByDesc('id')
                ->limit(40)
                ->get();

            foreach ($handovers as $h) {
                $isKitchen = $h->isKitchenTarget();
                $target = $isKitchen ? 'Kitchen' : 'Middo';
                if ($h->status === CashHandover::STATUS_PENDING || $h->status === CashHandover::STATUS_PROPOSED_REJECT) {
                    if ($isKitchen) {
                        $kitchenPending++;
                    } else {
                        $middoPending++;
                    }
                }
                if ($h->status === CashHandover::STATUS_ACCEPTED) {
                    if ($isKitchen) {
                        $kitchenAccepted++;
                    } else {
                        $middoAccepted++;
                    }
                }

                $rows[] = [
                    'id' => $h->id,
                    'label' => 'Handover #'.$h->id.' · '.($h->rider?->name ?? 'Rider'),
                    'meta' => $target.' · '.str($h->status)->replace('_', ' ')->headline().' · ৳'.number_format((int) $h->amount),
                    'badge' => $target,
                    'tone' => in_array($h->status, [CashHandover::STATUS_PENDING, CashHandover::STATUS_PROPOSED_REJECT], true) ? 'amber' : 'emerald',
                    'link' => 'handover',
                ];
            }
        }

        return self::section(
            'h',
            'Cash: Delivery → Kitchen / Middo',
            'Handovers tied to orders on this delivery date (kitchen + Middo targets).',
            'cash-handovers',
            $rows,
            [
                'kitchen_pending' => $kitchenPending,
                'middo_pending' => $middoPending,
                'kitchen_accepted' => $kitchenAccepted,
                'middo_accepted' => $middoAccepted,
            ],
            $kitchenPending + $middoPending
        );
    }

    /**
     * @param  list<string>  $actions
     * @return list<array<string,mixed>>
     */
    protected static function boxLogRows(string $date, array $actions, int $limit, string $badge, string $tone): array
    {
        if (! Schema::hasTable('middo_box_logs')) {
            return [];
        }

        return MiddoBoxLog::query()
            ->with('middoBox')
            ->whereIn('log_action', $actions)
            ->whereDate('created_at', $date)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (MiddoBoxLog $log) => [
                'id' => $log->middo_box_id,
                'label' => $log->middoBox?->qr_code_id ?? ('Box #'.$log->middo_box_id),
                'meta' => str($log->log_action)->replace('_', ' ')->headline()->toString()
                    .($log->order_id ? ' · order #'.$log->order_id : ''),
                'badge' => $badge,
                'tone' => $tone,
                'link' => 'box',
            ])
            ->all();
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  array<string,int>  $counts
     * @return array<string,mixed>
     */
    protected static function section(
        string $id,
        string $title,
        string $blurb,
        string $routeSuffix,
        array $rows,
        array $counts,
        int $attention,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'blurb' => $blurb,
            'route_suffix' => $routeSuffix,
            'rows' => $rows,
            'counts' => $counts,
            'attention' => $attention,
            'count' => count($rows),
        ];
    }
}
