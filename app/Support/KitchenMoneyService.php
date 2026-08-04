<?php

namespace App\Support;

use App\Models\KitchenMiddoTransfer;
use App\Models\KitchenWithdrawalRequest;
use App\Models\PartnerPayable;
use Illuminate\Support\Facades\DB;

class KitchenMoneyService
{
    /**
     * Approve a kitchen withdrawal: settle FIFO open payables (whole rows),
     * debit Middo cash once, debit kitchen receivable ledger once.
     */
    public static function approveWithdrawal(KitchenWithdrawalRequest $request, int $actorId, ?string $reviewNotes = null): KitchenWithdrawalRequest
    {
        return DB::transaction(function () use ($request, $actorId, $reviewNotes) {
            /** @var KitchenWithdrawalRequest $locked */
            $locked = KitchenWithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw new \RuntimeException('Withdrawal request is not pending.');
            }

            $kitchenId = (int) $locked->kitchen_user_id;
            $requested = (int) $locked->amount;
            $balance = KitchenAccountLedger::balance($kitchenId);
            if ($requested > $balance) {
                throw new \RuntimeException('Kitchen receivable is lower than the requested withdrawal.');
            }

            $open = PartnerPayable::query()
                ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
                ->where('beneficiary_user_id', $kitchenId)
                ->where('status', PartnerPayable::STATUS_OPEN)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $toSettle = [];
            $remaining = $requested;
            foreach ($open as $payable) {
                if ((int) $payable->amount > $remaining) {
                    break;
                }
                $toSettle[] = $payable;
                $remaining -= (int) $payable->amount;
            }

            $paid = $requested - $remaining;
            if ($paid < 1) {
                throw new \RuntimeException(
                    'Requested amount does not match a FIFO set of whole open payables. Adjust the amount to a payable total.'
                );
            }

            foreach ($toSettle as $payable) {
                OrderMoneyFlow::settlePayable($payable, $actorId, 'Kitchen withdrawal #'.$locked->id, [
                    'debit_middo' => false,
                    'debit_kitchen_ledger' => false,
                ]);
            }

            $middoEntry = MiddoCashLedger::debit(
                $paid,
                'kitchen_withdrawal_paid',
                KitchenWithdrawalRequest::class,
                $locked->id,
                "Kitchen withdrawal #{$locked->id} paid",
                $actorId
            );

            $kitchenEntry = KitchenAccountLedger::debit(
                $kitchenId,
                $paid,
                'withdrawal_paid',
                KitchenWithdrawalRequest::class,
                $locked->id,
                "Withdrawal #{$locked->id} approved",
                $actorId
            );

            $locked->update([
                'amount' => $paid,
                'status' => KitchenWithdrawalRequest::STATUS_APPROVED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
                'kitchen_ledger_entry_id' => $kitchenEntry->id,
                'middo_cash_ledger_entry_id' => $middoEntry->id,
            ]);

            return $locked->fresh();
        });
    }

    public static function rejectWithdrawal(KitchenWithdrawalRequest $request, int $actorId, ?string $reviewNotes = null): KitchenWithdrawalRequest
    {
        if (! $request->isPending()) {
            throw new \RuntimeException('Withdrawal request is not pending.');
        }

        $request->update([
            'status' => KitchenWithdrawalRequest::STATUS_REJECTED,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        return $request->fresh();
    }

    public static function confirmTransfer(KitchenMiddoTransfer $transfer, int $actorId, ?string $reviewNotes = null): KitchenMiddoTransfer
    {
        return DB::transaction(function () use ($transfer, $actorId, $reviewNotes) {
            /** @var KitchenMiddoTransfer $locked */
            $locked = KitchenMiddoTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw new \RuntimeException('Transfer is not pending.');
            }

            $entry = MiddoCashLedger::credit(
                (int) $locked->amount,
                'kitchen_transfer_confirmed',
                KitchenMiddoTransfer::class,
                $locked->id,
                "Kitchen transfer #{$locked->id} confirmed",
                $actorId
            );

            // Kitchen paid Middo cash they were holding → credit kitchen wallet (reduce debt / Middo owes more).
            KitchenAccountLedger::credit(
                (int) $locked->kitchen_user_id,
                (int) $locked->amount,
                'transfer_confirmed',
                KitchenMiddoTransfer::class,
                $locked->id,
                "Transfer #{$locked->id} to Middo confirmed",
                $actorId
            );

            $locked->update([
                'status' => KitchenMiddoTransfer::STATUS_CONFIRMED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
                'middo_cash_ledger_entry_id' => $entry->id,
            ]);

            return $locked->fresh();
        });
    }

    public static function rejectTransfer(KitchenMiddoTransfer $transfer, int $actorId, ?string $reviewNotes = null): KitchenMiddoTransfer
    {
        if (! $transfer->isPending()) {
            throw new \RuntimeException('Transfer is not pending.');
        }

        $transfer->update([
            'status' => KitchenMiddoTransfer::STATUS_REJECTED,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        return $transfer->fresh();
    }
}
