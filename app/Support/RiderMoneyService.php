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
     * Create a withdrawal and immediately debit rider wallet receivable.
     *
     * @param  array<string, mixed>  $payoutDetails
     */
    public static function requestWithdrawal(
        int $riderUserId,
        int $amount,
        string $payoutChannel,
        array $payoutDetails = [],
        ?string $notes = null,
        ?int $actorId = null,
    ): RiderWithdrawalRequest {
        PayoutChannel::assertValid($payoutChannel, $payoutDetails);
        $details = PayoutChannel::normalizeDetails($payoutChannel, $payoutDetails);
        $actorId = $actorId ?: $riderUserId;

        return DB::transaction(function () use ($riderUserId, $amount, $payoutChannel, $details, $notes, $actorId) {
            $rider = User::query()->whereKey($riderUserId)->lockForUpdate()->firstOrFail();
            if ((int) $rider->balance > 0) {
                throw new \RuntimeException('Hand over Due to Middo cash first, then request payment.');
            }

            if ($amount < 1) {
                throw new \RuntimeException('Nothing to withdraw — Middo does not currently owe you.');
            }

            $wallet = RiderAccountLedger::balance($riderUserId);
            if ($amount > $wallet) {
                throw new \RuntimeException("Requested ৳{$amount} exceeds wallet ৳{$wallet}.");
            }

            $request = RiderWithdrawalRequest::create([
                'rider_user_id' => $riderUserId,
                'amount' => $amount,
                'status' => RiderWithdrawalRequest::STATUS_PENDING,
                'notes' => $notes,
                'payout_channel' => $payoutChannel,
                'payout_details' => $details ?: null,
            ]);

            $riderEntry = RiderAccountLedger::debit(
                $riderUserId,
                $amount,
                'withdrawal_requested',
                RiderWithdrawalRequest::class,
                $request->id,
                "Withdrawal #{$request->id} requested",
                $actorId
            );

            $request->update(['rider_ledger_entry_id' => $riderEntry->id]);

            return $request->fresh();
        });
    }

    /**
     * Approve a rider withdrawal: settle FIFO open lunch payables that fit,
     * then debit Middo cash or bank. Wallet was already debited on request.
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

            $locked->update([
                'status' => RiderWithdrawalRequest::STATUS_APPROVED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
                'attachment_path' => $payout['attachment_path'],
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
        return DB::transaction(function () use ($request, $actorId, $reviewNotes) {
            /** @var RiderWithdrawalRequest $locked */
            $locked = RiderWithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw new \RuntimeException('Withdrawal request is not pending.');
            }

            RiderAccountLedger::credit(
                (int) $locked->rider_user_id,
                (int) $locked->amount,
                'withdrawal_rejected',
                RiderWithdrawalRequest::class,
                $locked->id,
                "Withdrawal #{$locked->id} rejected — wallet restored",
                $actorId
            );

            $locked->update([
                'status' => RiderWithdrawalRequest::STATUS_REJECTED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
            ]);

            return $locked->fresh();
        });
    }
}
