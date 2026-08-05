<?php

namespace App\Support;

use App\Models\MiddoBankLedgerEntry;
use App\Models\MiddoCashLedgerEntry;
use App\Models\MiddoOperatingCost;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F0e period P&L rollup for accounts.
 *
 * Order economics: delivery_date in range (non-cancelled).
 * Operating costs / cash / bank movements: created_at in the same calendar window (Asia/Dhaka).
 */
class PeriodPnl
{
    /**
     * @return array{
     *   from:string,
     *   to:string,
     *   timezone:string,
     *   order_count:int,
     *   lines:list<array{key:string,label:string,amount:int,section:string,note:?string}>,
     *   contribution:int,
     *   cash_by_type:list<array{entry_type:string,amount:int}>,
     *   bank_by_type:list<array{entry_type:string,amount:int,fee_amount:int}>,
     *   positions:array
     * }
     */
    public static function forRange(string $from, string $to): array
    {
        $tz = 'Asia/Dhaka';
        $fromDate = Carbon::parse($from, $tz)->startOfDay();
        $toDate = Carbon::parse($to, $tz)->endOfDay();
        if ($toDate->lt($fromDate)) {
            throw new \InvalidArgumentException('End date must be on or after start date.');
        }

        $orders = self::orderTotals($fromDate->toDateString(), $toDate->toDateString());
        $operating = self::operatingCostsTotal($fromDate, $toDate);
        $epsFees = self::epsFeesTotal($fromDate, $toDate);

        $foodExVat = (int) $orders['food_amount'] - (int) $orders['vat_amount'];
        $middoRest = (int) $orders['middo_rest_amount'];
        $contribution = $middoRest - $operating - $epsFees;

        $lines = [
            [
                'key' => 'food_ex_vat',
                'label' => 'Food revenue (ex-VAT)',
                'amount' => $foodExVat,
                'section' => 'revenue',
                'note' => 'Inclusive food less unbundled VAT',
            ],
            [
                'key' => 'charges',
                'label' => 'Charges',
                'amount' => (int) $orders['charges_amount'],
                'section' => 'revenue',
                'note' => null,
            ],
            [
                'key' => 'discounts',
                'label' => 'Discounts',
                'amount' => -1 * (int) $orders['discount_amount'],
                'section' => 'revenue',
                'note' => null,
            ],
            [
                'key' => 'vat',
                'label' => 'VAT (tax payable)',
                'amount' => (int) $orders['vat_amount'],
                'section' => 'tax',
                'note' => 'Unbundled from inclusive food — not Middo margin',
            ],
            [
                'key' => 'kitchen_share',
                'label' => 'Kitchen share',
                'amount' => -1 * (int) $orders['kitchen_share_amount'],
                'section' => 'cogs',
                'note' => null,
            ],
            [
                'key' => 'delivery_share',
                'label' => 'Delivery share (lunch)',
                'amount' => -1 * (int) $orders['delivery_share_amount'],
                'section' => 'cogs',
                'note' => null,
            ],
            [
                'key' => 'direct_cost',
                'label' => 'Direct cost (memo)',
                'amount' => -1 * (int) $orders['direct_cost_amount'],
                'section' => 'memo',
                'note' => 'Memo only — not subtracted from middo_rest',
            ],
            [
                'key' => 'middo_rest',
                'label' => 'Middo rest',
                'amount' => $middoRest,
                'section' => 'margin',
                'note' => 'billNet − VAT − kitchen − delivery',
            ],
            [
                'key' => 'operating_costs',
                'label' => 'Operating costs (box/custom)',
                'amount' => -1 * $operating,
                'section' => 'expense',
                'note' => 'middo_operating_costs booked in period',
            ],
            [
                'key' => 'eps_fees',
                'label' => 'EPS gateway fees',
                'amount' => -1 * $epsFees,
                'section' => 'expense',
                'note' => 'fee_amount on eps_in_net bank entries',
            ],
            [
                'key' => 'contribution',
                'label' => 'Contribution (middo_rest − opex − EPS fees)',
                'amount' => $contribution,
                'section' => 'result',
                'note' => null,
            ],
        ];

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'timezone' => $tz,
            'order_count' => (int) $orders['order_count'],
            'lines' => $lines,
            'contribution' => $contribution,
            'cash_by_type' => self::cashByType($fromDate, $toDate),
            'bank_by_type' => self::bankByType($fromDate, $toDate),
            'positions' => CashPositions::snapshot(),
        ];
    }

    /**
     * @return array{
     *   order_count:int|string,
     *   food_amount:int|string,
     *   vat_amount:int|string,
     *   charges_amount:int|string,
     *   discount_amount:int|string,
     *   kitchen_share_amount:int|string,
     *   delivery_share_amount:int|string,
     *   direct_cost_amount:int|string,
     *   middo_rest_amount:int|string
     * }
     */
    protected static function orderTotals(string $fromDate, string $toDate): array
    {
        $empty = [
            'order_count' => 0,
            'food_amount' => 0,
            'vat_amount' => 0,
            'charges_amount' => 0,
            'discount_amount' => 0,
            'kitchen_share_amount' => 0,
            'delivery_share_amount' => 0,
            'direct_cost_amount' => 0,
            'middo_rest_amount' => 0,
        ];

        if (! Schema::hasTable('orders')) {
            return $empty;
        }

        $row = Order::query()
            ->whereDate('delivery_date', '>=', $fromDate)
            ->whereDate('delivery_date', '<=', $toDate)
            ->where('order_status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('COALESCE(SUM(food_amount), 0) as food_amount')
            ->selectRaw('COALESCE(SUM(vat_amount), 0) as vat_amount')
            ->selectRaw('COALESCE(SUM(charges_amount), 0) as charges_amount')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_amount')
            ->selectRaw('COALESCE(SUM(kitchen_share_amount), 0) as kitchen_share_amount')
            ->selectRaw('COALESCE(SUM(delivery_share_amount), 0) as delivery_share_amount')
            ->selectRaw('COALESCE(SUM(direct_cost_amount), 0) as direct_cost_amount')
            ->selectRaw('COALESCE(SUM(middo_rest_amount), 0) as middo_rest_amount')
            ->first();

        return $row ? $row->toArray() : $empty;
    }

    protected static function operatingCostsTotal(Carbon $from, Carbon $to): int
    {
        if (! Schema::hasTable('middo_operating_costs')) {
            return 0;
        }

        return (int) MiddoOperatingCost::query()
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->sum('amount');
    }

    protected static function epsFeesTotal(Carbon $from, Carbon $to): int
    {
        if (! Schema::hasTable('middo_bank_ledger')) {
            return 0;
        }

        return (int) MiddoBankLedgerEntry::query()
            ->where('entry_type', MiddoBankLedgerEntry::TYPE_EPS_IN_NET)
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->sum('fee_amount');
    }

    /**
     * @return list<array{entry_type:string,amount:int}>
     */
    protected static function cashByType(Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('middo_cash_ledger')) {
            return [];
        }

        return MiddoCashLedgerEntry::query()
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->select('entry_type', DB::raw('COALESCE(SUM(amount), 0) as amount'))
            ->groupBy('entry_type')
            ->orderBy('entry_type')
            ->get()
            ->map(fn ($row) => [
                'entry_type' => (string) $row->entry_type,
                'amount' => (int) $row->amount,
            ])
            ->all();
    }

    /**
     * @return list<array{entry_type:string,amount:int,fee_amount:int}>
     */
    protected static function bankByType(Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('middo_bank_ledger')) {
            return [];
        }

        return MiddoBankLedgerEntry::query()
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->select(
                'entry_type',
                DB::raw('COALESCE(SUM(amount), 0) as amount'),
                DB::raw('COALESCE(SUM(fee_amount), 0) as fee_amount')
            )
            ->groupBy('entry_type')
            ->orderBy('entry_type')
            ->get()
            ->map(fn ($row) => [
                'entry_type' => (string) $row->entry_type,
                'amount' => (int) $row->amount,
                'fee_amount' => (int) $row->fee_amount,
            ])
            ->all();
    }
}
