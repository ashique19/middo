<?php

namespace App\Support;

use App\Models\CashHandover;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashHandoverActions
{
    /**
     * Accept a pending Middo Due handover into Middo cash.
     */
    public static function acceptMiddo(CashHandover $handover, int $actorId): CashHandover
    {
        return DB::transaction(function () use ($handover, $actorId) {
            $locked = CashHandover::query()
                ->with('items.order')
                ->whereKey($handover->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->isPending()) {
                throw new \RuntimeException('This cash handover is no longer pending.');
            }

            if (! $locked->isMiddoTarget()) {
                throw new \RuntimeException('This handover is for kitchen, not Middo.');
            }

            $rider = User::query()->whereKey($locked->rider_id)->lockForUpdate()->firstOrFail();

            if ((int) $rider->balance < (int) $locked->amount) {
                throw new \RuntimeException('Rider Due balance is insufficient for this handover.');
            }

            $rider->decrement('balance', (int) $locked->amount);

            MiddoCashLedger::credit(
                (int) $locked->amount,
                'rider_cash_handover',
                CashHandover::class,
                $locked->id,
                "Due cash handover #{$locked->id} from rider #{$rider->id}",
                $actorId
            );

            $locked->update([
                'status' => CashHandover::STATUS_ACCEPTED,
                'accepted_by' => $actorId,
                'accepted_at' => now(),
                'rejection_proposed_by' => null,
                'rejection_proposed_at' => null,
            ]);

            OrderMoneyFlow::recordCashHandoverToMiddo($locked->fresh(['items.order']), $actorId);

            return $locked->fresh(['items.order', 'rider']);
        });
    }

    /**
     * Ops proposes reject — does not free orders until accounts confirms.
     */
    public static function proposeRejectMiddo(CashHandover $handover, int $actorId, ?string $reason = null): CashHandover
    {
        return DB::transaction(function () use ($handover, $actorId, $reason) {
            $locked = CashHandover::query()
                ->whereKey($handover->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->isPending()) {
                throw new \RuntimeException('This cash handover is no longer pending.');
            }

            if (! $locked->isMiddoTarget()) {
                throw new \RuntimeException('This handover is for kitchen, not Middo.');
            }

            $reason = trim((string) ($reason ?? ''));
            $notes = trim((string) ($locked->notes ?? ''));
            if ($reason !== '') {
                $prefix = $notes !== '' ? $notes."\n" : '';
                $notes = $prefix.'Reject proposed: '.$reason;
            }

            $locked->update([
                'status' => CashHandover::STATUS_PROPOSED_REJECT,
                'rejection_proposed_by' => $actorId,
                'rejection_proposed_at' => now(),
                'notes' => $notes !== '' ? $notes : $locked->notes,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Accounts confirms proposed reject and frees orders for rider re-submit.
     */
    public static function confirmRejectMiddo(CashHandover $handover, int $actorId, ?string $reason = null): CashHandover
    {
        return DB::transaction(function () use ($handover, $actorId, $reason) {
            $locked = CashHandover::query()
                ->whereKey($handover->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->isProposedReject()) {
                throw new \RuntimeException('This handover has no pending reject proposal to confirm.');
            }

            if (! $locked->isMiddoTarget()) {
                throw new \RuntimeException('This handover is for kitchen, not Middo.');
            }

            $notes = trim((string) ($locked->notes ?? ''));
            $reason = trim((string) ($reason ?? ''));
            if ($reason !== '') {
                $prefix = $notes !== '' ? $notes."\n" : '';
                $notes = $prefix.'Reject confirmed: '.$reason;
            } else {
                $prefix = $notes !== '' ? $notes."\n" : '';
                $notes = $prefix.'Reject confirmed by accounts.';
            }

            $locked->items()->delete();

            $locked->update([
                'status' => CashHandover::STATUS_REJECTED,
                'accepted_by' => $actorId,
                'accepted_at' => now(),
                'notes' => $notes !== '' ? $notes : $locked->notes,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Accounts dismisses a proposed reject — handover returns to pending.
     */
    public static function dismissProposeRejectMiddo(CashHandover $handover, int $actorId): CashHandover
    {
        return DB::transaction(function () use ($handover, $actorId) {
            $locked = CashHandover::query()
                ->whereKey($handover->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->isProposedReject()) {
                throw new \RuntimeException('This handover has no pending reject proposal to dismiss.');
            }

            $notes = trim((string) ($locked->notes ?? ''));
            $prefix = $notes !== '' ? $notes."\n" : '';
            $notes = $prefix.'Reject proposal dismissed by #'.$actorId.'.';

            $locked->update([
                'status' => CashHandover::STATUS_PENDING,
                'rejection_proposed_by' => null,
                'rejection_proposed_at' => null,
                'notes' => $notes,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Reject a pending handover and free its orders for a new submission.
     * Used for kitchen-target handovers (immediate). Middo uses propose/confirm.
     */
    public static function reject(CashHandover $handover, int $actorId, ?string $reason = null): CashHandover
    {
        return DB::transaction(function () use ($handover, $actorId, $reason) {
            $locked = CashHandover::query()
                ->whereKey($handover->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->isPending()) {
                throw new \RuntimeException('This cash handover is no longer pending.');
            }

            $notes = trim((string) ($locked->notes ?? ''));
            $reason = trim((string) ($reason ?? ''));
            if ($reason !== '') {
                $prefix = $notes !== '' ? $notes."\n" : '';
                $notes = $prefix.'Rejected: '.$reason;
            }

            // Free order_id unique so rider can re-submit Due for these orders.
            $locked->items()->delete();

            $locked->update([
                'status' => CashHandover::STATUS_REJECTED,
                'accepted_by' => $actorId,
                'accepted_at' => now(),
                'notes' => $notes !== '' ? $notes : $locked->notes,
            ]);

            return $locked->fresh();
        });
    }
}
