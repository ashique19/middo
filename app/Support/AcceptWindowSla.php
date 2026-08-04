<?php

namespace App\Support;

use App\Models\OrderGroup;
use App\Models\OrderGroupEvent;
use App\Models\User;
use Carbon\Carbon;

class AcceptWindowSla
{
    /**
     * Find open pool groups in the warn window and alert eligible kitchens once.
     *
     * @return array{groups_checked: int, alerts_created: int}
     */
    public static function warnEligible(?Carbon $now = null): array
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');
        $warnMinutes = MiddoSettings::acceptWindowWarnMinutes();

        $groups = OrderGroup::query()
            ->with(['orders', 'menuItem'])
            ->whereNull('kitchen_id')
            ->whereHas('orders', fn ($q) => $q->where('order_status', '!=', 'cancelled'))
            ->whereDate('delivery_date', '>=', $now->toDateString())
            ->orderBy('id')
            ->get();

        $kitchens = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))
            ->where('status', 'active')
            ->get();

        $alertsCreated = 0;
        $groupsChecked = 0;

        foreach ($groups as $group) {
            if (! KitchenAcceptWindow::isOpen($group, $now)) {
                continue;
            }

            if (! KitchenAcceptWindow::isClosingSoon($group, $now, $warnMinutes)) {
                continue;
            }

            $groupsChecked++;
            $declinedBy = array_flip(self::declinedKitchenIdsForGroupToday((int) $group->id));

            foreach ($kitchens as $kitchen) {
                if (isset($declinedBy[(int) $kitchen->id])) {
                    continue;
                }

                if (KitchenCapacity::remainingSlots($kitchen) < 1) {
                    continue;
                }

                $alert = StaffAlerts::notifyKitchenAcceptWindowClosing($group, $kitchen);
                if ($alert) {
                    $alertsCreated++;
                }
            }
        }

        return [
            'groups_checked' => $groupsChecked,
            'alerts_created' => $alertsCreated,
        ];
    }

    /**
     * @return list<int>
     */
    protected static function declinedKitchenIdsForGroupToday(int $groupId): array
    {
        $dayStart = now('Asia/Dhaka')->startOfDay()->timezone('UTC');
        $dayEnd = now('Asia/Dhaka')->endOfDay()->timezone('UTC');

        return OrderGroupEvent::query()
            ->where('order_group_id', $groupId)
            ->where('type', OrderGroupEvent::TYPE_DECLINE)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->pluck('kitchen_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
