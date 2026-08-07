<?php

namespace App\Support;

use App\Models\CashHandover;
use App\Models\KitchenAccountLedgerEntry;
use App\Models\PartnerPayable;
use App\Models\RiderAccountLedgerEntry;
use App\Models\User;
use Illuminate\Support\Collection;

class PartnerLedgerPresentation
{
    public const FILTER_ALL = 'all';

    public const FILTER_CASH_IN = 'cash-in';

    public const FILTER_CASH_OUT = 'cash-out';

    /**
     * @return list<string>
     */
    public static function filters(): array
    {
        return [self::FILTER_ALL, self::FILTER_CASH_IN, self::FILTER_CASH_OUT];
    }

    public static function filterLabel(string $filter): string
    {
        return match ($filter) {
            self::FILTER_CASH_IN => 'cash-in',
            self::FILTER_CASH_OUT => 'cash-out',
            default => 'all',
        };
    }

    public static function directionForKitchen(string $entryType): string
    {
        return match ($entryType) {
            'share_accrued',
            'transfer_confirmed',
            'withdrawal_rejected',
            'cash_received' => self::FILTER_CASH_IN,
            default => self::FILTER_CASH_OUT,
        };
    }

    public static function directionForRider(string $entryType): string
    {
        return match ($entryType) {
            'commission_accrued',
            'withdrawal_rejected',
            'operating_cost_reimbursed' => self::FILTER_CASH_IN,
            default => self::FILTER_CASH_OUT,
        };
    }

    /**
     * @param  Collection<int, KitchenAccountLedgerEntry>  $entries
     * @return Collection<int, object>
     */
    public static function presentKitchenEntries(Collection $entries): Collection
    {
        $handoverIds = $entries
            ->filter(fn (KitchenAccountLedgerEntry $e) => $e->reference_type === CashHandover::class && $e->reference_id)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $payableIds = $entries
            ->filter(fn (KitchenAccountLedgerEntry $e) => $e->reference_type === PartnerPayable::class && $e->reference_id)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $handovers = $handoverIds->isEmpty()
            ? collect()
            : CashHandover::query()
                ->with(['rider:id,first_name,last_name'])
                ->whereIn('id', $handoverIds)
                ->get()
                ->keyBy('id');

        $payableOrderIds = $payableIds->isEmpty()
            ? collect()
            : PartnerPayable::query()
                ->whereIn('id', $payableIds)
                ->pluck('order_id', 'id');

        return $entries->map(function (KitchenAccountLedgerEntry $entry) use ($handovers, $payableOrderIds) {
            $direction = self::directionForKitchen((string) $entry->entry_type);
            $orderId = $entry->reference_type === PartnerPayable::class
                ? $payableOrderIds->get($entry->reference_id)
                : null;
            $handover = $entry->reference_type === CashHandover::class
                ? $handovers->get($entry->reference_id)
                : null;

            return (object) [
                'id' => $entry->id,
                'created_at' => $entry->created_at,
                'direction' => $direction,
                'summary' => self::kitchenSummary($entry, $handover, $orderId),
                'amount' => abs((int) $entry->amount),
                'balance_after' => (int) $entry->balance_after,
            ];
        });
    }

    /**
     * @param  Collection<int, RiderAccountLedgerEntry>  $entries
     * @return Collection<int, object>
     */
    public static function presentRiderEntries(Collection $entries): Collection
    {
        $payableIds = $entries
            ->filter(fn (RiderAccountLedgerEntry $e) => $e->reference_type === PartnerPayable::class && $e->reference_id)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $payableOrderIds = $payableIds->isEmpty()
            ? collect()
            : PartnerPayable::query()
                ->whereIn('id', $payableIds)
                ->pluck('order_id', 'id');

        return $entries->map(function (RiderAccountLedgerEntry $entry) use ($payableOrderIds) {
            $direction = self::directionForRider((string) $entry->entry_type);
            $orderId = $entry->reference_type === PartnerPayable::class
                ? $payableOrderIds->get($entry->reference_id)
                : null;

            return (object) [
                'id' => $entry->id,
                'created_at' => $entry->created_at,
                'direction' => $direction,
                'summary' => self::riderSummary($entry, $orderId),
                'amount' => abs((int) $entry->amount),
                'balance_after' => (int) $entry->balance_after,
            ];
        });
    }

    protected static function kitchenSummary(KitchenAccountLedgerEntry $entry, ?CashHandover $handover, mixed $orderId): string
    {
        $orderSuffix = $orderId ? ' #'.$orderId : self::orderSuffixFromDescription($entry->description);

        return match ((string) $entry->entry_type) {
            'cash_received' => 'From rider '.self::riderDisplayName($handover?->rider),
            'share_accrued' => 'Order share'.$orderSuffix,
            'transfer_confirmed' => 'Transfer to Middo confirmed',
            'withdrawal_requested' => 'Withdraw req',
            'withdrawal_paid' => 'Withdraw paid',
            'withdrawal_rejected' => 'Withdraw rejected',
            'payable_settled' => 'Share paid'.$orderSuffix,
            'settlement_batch_paid' => 'Settlement paid',
            default => $entry->description
                ?: str((string) $entry->entry_type)->replace('_', ' ')->headline()->toString(),
        };
    }

    protected static function riderSummary(RiderAccountLedgerEntry $entry, mixed $orderId): string
    {
        $orderSuffix = $orderId ? ' #'.$orderId : self::orderSuffixFromDescription($entry->description);

        return match ((string) $entry->entry_type) {
            'commission_accrued' => 'Commission'.$orderSuffix,
            'commission_settled_in_kind' => 'Commission settled'.$orderSuffix,
            'share_voided' => 'Share voided',
            'withdrawal_requested' => 'Withdraw req',
            'withdrawal_paid' => 'Withdraw paid',
            'withdrawal_rejected' => 'Withdraw rejected',
            default => $entry->description
                ?: str((string) $entry->entry_type)->replace('_', ' ')->headline()->toString(),
        };
    }

    protected static function riderDisplayName(?User $rider): string
    {
        if (! $rider) {
            return 'unknown';
        }

        $name = trim((string) $rider->name);

        return $name !== '' ? $name : 'unknown';
    }

    protected static function orderSuffixFromDescription(?string $description): string
    {
        if (is_string($description) && preg_match('/order #(\d+)/i', $description, $m)) {
            return ' #'.$m[1];
        }

        return '';
    }
}
