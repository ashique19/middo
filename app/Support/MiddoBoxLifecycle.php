<?php

namespace App\Support;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Lifespan / efficiency helpers for a Middo box detail page.
 */
class MiddoBoxLifecycle
{
    /**
     * @return array{
     *   created_at: Carbon|null,
     *   damaged_at: Carbon|null,
     *   retired_at: Carbon|null,
     *   days_in_service: int,
     *   run_count: int,
     *   uses_recorded: int,
     *   runs_per_day: float|null,
     *   unit_cost: int|null,
     *   cost_per_run: float|null,
     *   location: string,
     *   status: string
     * }
     */
    public static function metrics(MiddoBox $box, ?Carbon $now = null): array
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');
        $created = $box->created_at?->copy()->timezone('Asia/Dhaka');
        $damaged = $box->damagedReportedAt()?->copy()->timezone('Asia/Dhaka');
        $retired = $box->retiredAt()?->copy()->timezone('Asia/Dhaka');

        $end = $retired ?? $damaged ?? $now;
        $days = 1;
        if ($created) {
            $days = max(1, (int) ceil(max(0.0, $created->floatDiffInDays($end))));
        }

        $runs = $box->runCount();
        $unitCost = null;
        if (Schema::hasColumn('middo_boxes', 'unit_cost_bdt')) {
            $raw = $box->getAttribute('unit_cost_bdt');
            $unitCost = $raw !== null && (int) $raw > 0 ? (int) $raw : null;
        }

        return [
            'created_at' => $created,
            'damaged_at' => $damaged,
            'retired_at' => $retired,
            'days_in_service' => $days,
            'run_count' => $runs,
            'uses_recorded' => (int) $box->total_uses_count,
            'runs_per_day' => $days > 0 ? round($runs / $days, 2) : null,
            'unit_cost' => $unitCost,
            'cost_per_run' => ($unitCost !== null && $runs > 0) ? round($unitCost / $runs, 2) : null,
            'location' => $box->locationLabel(),
            'status' => (string) $box->asset_status,
        ];
    }

    /**
     * Tracking tree rows, latest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function trackingTree(MiddoBox $box): Collection
    {
        $box->loadMissing(['logs.order.menuItem', 'logs.performedBy', 'requestBox.rider']);

        return $box->logs
            ->sortByDesc('id')
            ->values()
            ->map(fn (MiddoBoxLog $log) => [
                'id' => $log->id,
                'action' => (string) $log->log_action,
                'action_label' => str((string) $log->log_action)->replace('_', ' ')->headline()->toString(),
                'custody' => (string) $log->custody_status,
                'notes' => self::displayNotes($log, $box),
                'order_id' => $log->order_id,
                'order_menu' => $log->order?->menuItem?->name,
                'actor' => $log->performedBy?->name,
                'at' => $log->created_at?->timezone('Asia/Dhaka'),
                'at_label' => $log->created_at?->timezone('Asia/Dhaka')->format('M d, Y g:i A'),
            ]);
    }

    protected static function displayNotes(MiddoBoxLog $log, MiddoBox $box): ?string
    {
        $notes = $log->notes;

        // Older staged logs omitted the rider; surface it from the request link when present.
        if ($log->log_action === 'staged_for_kitchen_pickup') {
            $riderName = $box->requestBox?->rider?->name;
            if ($riderName && ($notes === null || ! str_contains($notes, $riderName))) {
                $notes = $notes
                    ? rtrim($notes).' · Rider: '.$riderName
                    : 'Ready for rider pickup by '.$riderName;
            }
        }

        return $notes;
    }
}
