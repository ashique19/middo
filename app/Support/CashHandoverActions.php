<?php

namespace App\Support;

use App\Models\CashHandover;
use Illuminate\Support\Facades\DB;

class CashHandoverActions
{
    /**
     * Reject a pending handover and free its orders for a new submission.
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
                'status' => 'rejected',
                'accepted_by' => $actorId,
                'accepted_at' => now(),
                'notes' => $notes !== '' ? $notes : $locked->notes,
            ]);

            return $locked->fresh();
        });
    }
}
