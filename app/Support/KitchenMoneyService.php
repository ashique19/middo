<?php

namespace App\Support;

use App\Models\KitchenMiddoTransfer;
use App\Models\KitchenSettlementBatch;
use App\Models\KitchenSettlementBatchItem;
use App\Models\KitchenWithdrawalRequest;
use App\Models\PartnerPayable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class KitchenMoneyService
{
    /**
     * Payable IDs reserved on a pending settlement batch (cannot withdraw or re-batch).
     *
     * @return list<int>
     */
    public static function reservedPayableIds(?int $kitchenUserId = null): array
    {
        $query = KitchenSettlementBatchItem::query()
            ->whereHas('batch', function ($q) use ($kitchenUserId) {
                $q->where('status', KitchenSettlementBatch::STATUS_PENDING);
                if ($kitchenUserId) {
                    $q->where('kitchen_user_id', $kitchenUserId);
                }
            });

        return $query->pluck('partner_payable_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Create a withdrawal and immediately debit kitchen receivable.
     *
     * @param  array<string, mixed>  $payoutDetails
     */
    public static function requestWithdrawal(
        int $kitchenUserId,
        int $amount,
        string $payoutChannel,
        array $payoutDetails = [],
        ?string $notes = null,
        ?int $actorId = null,
    ): KitchenWithdrawalRequest {
        PayoutChannel::assertValid($payoutChannel, $payoutDetails);
        $details = PayoutChannel::normalizeDetails($payoutChannel, $payoutDetails);
        $actorId = $actorId ?: $kitchenUserId;

        return DB::transaction(function () use ($kitchenUserId, $amount, $payoutChannel, $details, $notes, $actorId) {
            if ($amount < 1) {
                throw new \RuntimeException('Nothing to withdraw — Middo does not currently owe you.');
            }

            $balance = KitchenAccountLedger::balance($kitchenUserId);
            if ($amount > $balance) {
                throw new \RuntimeException("Requested ৳{$amount} exceeds what Middo owes you (৳{$balance}).");
            }

            $request = KitchenWithdrawalRequest::create([
                'kitchen_user_id' => $kitchenUserId,
                'amount' => $amount,
                'status' => KitchenWithdrawalRequest::STATUS_PENDING,
                'notes' => $notes,
                'payout_channel' => $payoutChannel,
                'payout_details' => $details ?: null,
            ]);

            $kitchenEntry = KitchenAccountLedger::debit(
                $kitchenUserId,
                $amount,
                'withdrawal_requested',
                KitchenWithdrawalRequest::class,
                $request->id,
                "Withdrawal #{$request->id} requested",
                $actorId
            );

            $request->update(['kitchen_ledger_entry_id' => $kitchenEntry->id]);

            return $request->fresh();
        });
    }

    /**
     * Approve a kitchen withdrawal: settle FIFO open payables (whole rows),
     * debit Middo cash or bank once. Receivable was already debited on request.
     *
     * @param  array{bank_account_id?: ?int, attachment?: ?UploadedFile}  $options
     */
    public static function approveWithdrawal(
        KitchenWithdrawalRequest $request,
        int $actorId,
        ?string $reviewNotes = null,
        array $options = [],
    ): KitchenWithdrawalRequest {
        return DB::transaction(function () use ($request, $actorId, $reviewNotes, $options) {
            /** @var KitchenWithdrawalRequest $locked */
            $locked = KitchenWithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw new \RuntimeException('Withdrawal request is not pending.');
            }

            $kitchenId = (int) $locked->kitchen_user_id;
            $requested = (int) $locked->amount;

            $reserved = self::reservedPayableIds($kitchenId);
            $open = PartnerPayable::query()
                ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
                ->where('beneficiary_user_id', $kitchenId)
                ->where('status', PartnerPayable::STATUS_OPEN)
                ->when($reserved !== [], fn ($q) => $q->whereNotIn('id', $reserved))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remaining = $requested;
            foreach ($open as $payable) {
                if ((int) $payable->amount > $remaining) {
                    break;
                }
                OrderMoneyFlow::settlePayable($payable, $actorId, 'Kitchen withdrawal #'.$locked->id, [
                    'debit_middo' => false,
                    'debit_kitchen_ledger' => false,
                ]);
                $remaining -= (int) $payable->amount;
            }

            $channel = (string) ($locked->payout_channel ?: PayoutChannel::CASH);
            $payout = WithdrawalPayout::debitMiddoFloat(
                $channel,
                $requested,
                KitchenWithdrawalRequest::class,
                (int) $locked->id,
                "Kitchen withdrawal #{$locked->id} paid",
                $actorId,
                isset($options['bank_account_id']) ? (int) $options['bank_account_id'] : null,
                $options['attachment'] ?? null,
                'kitchen_withdrawal_paid',
            );

            $locked->update([
                'status' => KitchenWithdrawalRequest::STATUS_APPROVED,
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
     *   middo_cash:int,
     *   open_payables_total:int,
     *   open_payables:list<array{id:int,order_id:int|null,amount:int}>,
     *   fifo_fit_total:int,
     *   fifo_ok:bool
     * }
     */
    public static function withdrawalPreview(KitchenWithdrawalRequest $request): array
    {
        $kitchenId = (int) $request->kitchen_user_id;
        $requested = (int) $request->amount;
        $reserved = self::reservedPayableIds($kitchenId);
        $open = PartnerPayable::query()
            ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
            ->where('beneficiary_user_id', $kitchenId)
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->when($reserved !== [], fn ($q) => $q->whereNotIn('id', $reserved))
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
            'wallet' => KitchenAccountLedger::balance($kitchenId),
            'middo_cash' => MiddoCashLedger::balance(),
            'open_payables_total' => (int) $open->sum('amount'),
            'open_payables' => $open->take(8)->map(fn (PartnerPayable $p) => [
                'id' => $p->id,
                'order_id' => $p->order_id,
                'amount' => (int) $p->amount,
            ])->all(),
            'fifo_fit_total' => $fifoFit,
            'fifo_ok' => $fifoFit >= 1 && $fifoFit <= $requested,
        ];
    }

    /**
     * @param  list<int>  $payableIds
     * @param  array<string, mixed>  $payoutDetails
     */
    public static function createSettlementBatch(
        int $kitchenUserId,
        string $name,
        array $payableIds,
        string $payoutChannel,
        array $payoutDetails,
        int $actorId,
        ?string $notes = null,
    ): KitchenSettlementBatch {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Batch name is required.');
        }

        $payableIds = array_values(array_unique(array_map('intval', $payableIds)));
        if ($payableIds === []) {
            throw new \InvalidArgumentException('Select at least one open payable.');
        }

        PayoutChannel::assertValid($payoutChannel, $payoutDetails);
        $details = PayoutChannel::normalizeDetails($payoutChannel, $payoutDetails);

        return DB::transaction(function () use ($kitchenUserId, $name, $payableIds, $payoutChannel, $details, $actorId, $notes) {
            $reserved = self::reservedPayableIds();
            $payables = PartnerPayable::query()
                ->whereIn('id', $payableIds)
                ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
                ->where('beneficiary_user_id', $kitchenUserId)
                ->where('status', PartnerPayable::STATUS_OPEN)
                ->when($reserved !== [], fn ($q) => $q->whereNotIn('id', $reserved))
                ->lockForUpdate()
                ->get();

            if ($payables->count() !== count($payableIds)) {
                throw new \RuntimeException('One or more payables are missing, settled, or already in a pending batch.');
            }

            $amount = (int) $payables->sum('amount');
            if ($amount < 1) {
                throw new \RuntimeException('Batch amount must be positive.');
            }

            $balance = KitchenAccountLedger::balance($kitchenUserId);
            if ($amount > $balance) {
                throw new \RuntimeException('Kitchen receivable is lower than the batch total.');
            }

            $batch = KitchenSettlementBatch::query()->create([
                'name' => $name,
                'kitchen_user_id' => $kitchenUserId,
                'amount' => $amount,
                'status' => KitchenSettlementBatch::STATUS_PENDING,
                'payout_channel' => $payoutChannel,
                'payout_details' => $details ?: null,
                'notes' => $notes,
                'created_by' => $actorId,
            ]);

            foreach ($payables as $payable) {
                KitchenSettlementBatchItem::query()->create([
                    'kitchen_settlement_batch_id' => $batch->id,
                    'partner_payable_id' => $payable->id,
                    'amount' => (int) $payable->amount,
                ]);
            }

            return $batch->fresh(['items']);
        });
    }

    /**
     * @param  array{bank_account_id?: ?int, attachment?: ?UploadedFile}  $options
     */
    public static function approveSettlementBatch(
        KitchenSettlementBatch $batch,
        int $actorId,
        ?string $reviewNotes = null,
        array $options = [],
    ): KitchenSettlementBatch {
        return DB::transaction(function () use ($batch, $actorId, $reviewNotes, $options) {
            /** @var KitchenSettlementBatch $locked */
            $locked = KitchenSettlementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw new \RuntimeException('Settlement batch is not pending.');
            }

            $locked->load(['items']);
            $kitchenId = (int) $locked->kitchen_user_id;
            $amount = (int) $locked->amount;
            $balance = KitchenAccountLedger::balance($kitchenId);
            if ($amount > $balance) {
                throw new \RuntimeException('Kitchen receivable is lower than the batch total.');
            }

            $payableIds = $locked->items->pluck('partner_payable_id')->map(fn ($id) => (int) $id)->all();
            $payables = PartnerPayable::query()
                ->whereIn('id', $payableIds)
                ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
                ->where('beneficiary_user_id', $kitchenId)
                ->where('status', PartnerPayable::STATUS_OPEN)
                ->lockForUpdate()
                ->get();

            if ($payables->count() !== count($payableIds)) {
                throw new \RuntimeException('Batch payables are no longer all open. Reject and rebuild the batch.');
            }

            foreach ($payables as $payable) {
                OrderMoneyFlow::settlePayable($payable, $actorId, 'Kitchen settlement batch #'.$locked->id, [
                    'debit_middo' => false,
                    'debit_kitchen_ledger' => false,
                ]);
            }

            $channel = (string) ($locked->payout_channel ?: PayoutChannel::CASH);
            $payout = WithdrawalPayout::debitMiddoFloat(
                $channel,
                $amount,
                KitchenSettlementBatch::class,
                (int) $locked->id,
                "Kitchen settlement batch #{$locked->id} paid",
                $actorId,
                isset($options['bank_account_id']) ? (int) $options['bank_account_id'] : null,
                $options['attachment'] ?? null,
                'kitchen_settlement_paid',
            );

            $kitchenEntry = KitchenAccountLedger::debit(
                $kitchenId,
                $amount,
                'settlement_batch_paid',
                KitchenSettlementBatch::class,
                $locked->id,
                "Settlement batch #{$locked->id} approved",
                $actorId
            );

            $locked->update([
                'status' => KitchenSettlementBatch::STATUS_APPROVED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
                'attachment_path' => $payout['attachment_path'],
                'kitchen_ledger_entry_id' => $kitchenEntry->id,
                'middo_cash_ledger_entry_id' => $payout['cash_entry_id'],
                'middo_bank_account_id' => $payout['bank_account_id'],
                'middo_bank_ledger_entry_id' => $payout['bank_entry_id'],
            ]);

            return $locked->fresh(['items']);
        });
    }

    public static function rejectSettlementBatch(
        KitchenSettlementBatch $batch,
        int $actorId,
        ?string $reviewNotes = null,
    ): KitchenSettlementBatch {
        if (! $batch->isPending()) {
            throw new \RuntimeException('Settlement batch is not pending.');
        }

        $batch->update([
            'status' => KitchenSettlementBatch::STATUS_REJECTED,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        // Items stay for audit; unique partner_payable_id blocks re-attach while row exists.
        // Delete items so payables can enter a new batch or withdrawal FIFO.
        KitchenSettlementBatchItem::query()
            ->where('kitchen_settlement_batch_id', $batch->id)
            ->delete();

        return $batch->fresh();
    }

    public static function rejectWithdrawal(KitchenWithdrawalRequest $request, int $actorId, ?string $reviewNotes = null): KitchenWithdrawalRequest
    {
        return DB::transaction(function () use ($request, $actorId, $reviewNotes) {
            /** @var KitchenWithdrawalRequest $locked */
            $locked = KitchenWithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw new \RuntimeException('Withdrawal request is not pending.');
            }

            KitchenAccountLedger::credit(
                (int) $locked->kitchen_user_id,
                (int) $locked->amount,
                'withdrawal_rejected',
                KitchenWithdrawalRequest::class,
                $locked->id,
                "Withdrawal #{$locked->id} rejected — receivable restored",
                $actorId
            );

            $locked->update([
                'status' => KitchenWithdrawalRequest::STATUS_REJECTED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
            ]);

            return $locked->fresh();
        });
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
