<?php

namespace App\Support;

use App\Models\PartnerPayable;
use App\Models\RiderWithdrawalRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RiderMoneyService
{
    /**
     * Approve a rider withdrawal: settle FIFO open lunch payables that fit,
     * then debit Middo cash or bank + rider wallet for the full requested amount.
     *
     * @param  array{bank_account_id?: ?int, attachment?: ?UploadedFile}  $options
     */
    public static function approveWithdrawal(
        RiderWithdrawalRequest $request,
        int $actorId,
        ?string $reviewNotes = null,
        array $options = [],
    ): RiderWithdrawalRequest {
        return DB::transaction(function () use ($request, $actorId, $reviewNotes, $options) {
            /** @var RiderWithdrawalRequest $locked */
            $locked = RiderWithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw new \RuntimeException('Withdrawal request is not pending.');
            }

            $riderId = (int) $locked->rider_user_id;
            $requested = (int) $locked->amount;
            $balance = RiderAccountLedger::balance($riderId);
            if ($requested > $balance) {
                throw new \RuntimeException('Rider wallet is lower than the requested withdrawal.');
            }

            $rider = User::query()->whereKey($riderId)->lockForUpdate()->firstOrFail();
            if ((int) $rider->balance > 0) {
                throw new \RuntimeException('Rider still holds Due to Middo cash. Clear handovers before paying withdrawals.');
            }

            $open = PartnerPayable::query()
                ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
                ->where('beneficiary_user_id', $riderId)
                ->where('status', PartnerPayable::STATUS_OPEN)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remaining = $requested;
            foreach ($open as $payable) {
                if ((int) $payable->amount > $remaining) {
                    break;
                }
                OrderMoneyFlow::settlePayable($payable, $actorId, 'Rider withdrawal #'.$locked->id, [
                    'debit_middo' => false,
                    'debit_kitchen_ledger' => false,
                    'debit_rider_ledger' => false,
                ]);
                $remaining -= (int) $payable->amount;
            }

            $channel = (string) ($locked->payout_channel ?: PayoutChannel::CASH);
            $payout = WithdrawalPayout::debitMiddoFloat(
                $channel,
                $requested,
                RiderWithdrawalRequest::class,
                (int) $locked->id,
                "Rider withdrawal #{$locked->id} paid",
                $actorId,
                isset($options['bank_account_id']) ? (int) $options['bank_account_id'] : null,
                $options['attachment'] ?? null,
                'rider_withdrawal_paid',
            );

            $riderEntry = RiderAccountLedger::debit(
                $riderId,
                $requested,
                'withdrawal_paid',
                RiderWithdrawalRequest::class,
                $locked->id,
                "Withdrawal #{$locked->id} approved",
                $actorId
            );

            $locked->update([
                'status' => RiderWithdrawalRequest::STATUS_APPROVED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
                'attachment_path' => $payout['attachment_path'],
                'rider_ledger_entry_id' => $riderEntry->id,
                'middo_cash_ledger_entry_id' => $payout['cash_entry_id'],
                'middo_bank_account_id' => $payout['bank_account_id'],
                'middo_bank_ledger_entry_id' => $payout['bank_entry_id'],
            ]);

            return $locked->fresh();
        });
    }

    /**
     * @return array{
     *   wallet:int,
     *   due_float:int,
     *   middo_cash:int,
     *   open_payables_total:int,
     *   open_payables:list<array{id:int,order_id:int|null,amount:int}>,
     *   fifo_fit_total:int,
     *   blocked_by_due:bool
     * }
     */
    public static function withdrawalPreview(RiderWithdrawalRequest $request): array
    {
        $riderId = (int) $request->rider_user_id;
        $requested = (int) $request->amount;
        $rider = User::query()->find($riderId);
        $open = PartnerPayable::query()
            ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
            ->where('beneficiary_user_id', $riderId)
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->orderBy('id')
            ->get();

        $remaining = $requested;
        $fifoFit = 0;
        foreach ($open as $payable) {
            if ((int) $payable->amount > $remaining) {
                break;
            }
            $fifoFit += (int) $payable->amount;
            $remaining -= (int) $payable->amount;
        }

        return [
            'wallet' => RiderAccountLedger::balance($riderId),
            'due_float' => (int) ($rider?->balance ?? 0),
            'middo_cash' => MiddoCashLedger::balance(),
            'open_payables_total' => (int) $open->sum('amount'),
            'open_payables' => $open->take(8)->map(fn (PartnerPayable $p) => [
                'id' => $p->id,
                'order_id' => $p->order_id,
                'amount' => (int) $p->amount,
            ])->all(),
            'fifo_fit_total' => $fifoFit,
            'blocked_by_due' => (int) ($rider?->balance ?? 0) > 0,
        ];
    }

    public static function rejectWithdrawal(RiderWithdrawalRequest $request, int $actorId, ?string $reviewNotes = null): RiderWithdrawalRequest
    {
        if (! $request->isPending()) {
            throw new \RuntimeException('Withdrawal request is not pending.');
        }

        $request->update([
            'status' => RiderWithdrawalRequest::STATUS_REJECTED,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        return $request->fresh();
    }
}
