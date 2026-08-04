<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupEvent;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class StaffAlerts
{
    public static function notifyKitchenAssigned(OrderGroup $group, User $kitchen): ?StaffAlert
    {
        $group->loadMissing('menuItem');

        return self::createOnce(
            (int) $kitchen->id,
            StaffAlert::TYPE_GROUP_ASSIGNED,
            'Order group assigned',
            sprintf(
                '%s (%s) was assigned to your kitchen.',
                $group->name,
                $group->menuItem?->name ?? 'menu'
            ),
            (int) $group->id,
            ['source' => 'ops_assign'],
            'assigned:'.$group->id.':'.$kitchen->id.':'.now('Asia/Dhaka')->toDateString()
        );
    }

    public static function notifyOpsNeedsReassignment(
        OrderGroup $group,
        string $eventType,
        User $kitchen,
        ?string $reason = null
    ): int {
        $group->loadMissing('menuItem');

        $label = match ($eventType) {
            OrderGroupEvent::TYPE_SHORTAGE => 'Shortage',
            OrderGroupEvent::TYPE_DECLINE => 'Decline',
            OrderGroupEvent::TYPE_RELEASE => 'Release',
            default => ucfirst($eventType),
        };

        $title = "{$label}: {$group->name}";
        $body = sprintf(
            '%s reported by %s%s. Group is in the open pool for reassignment.',
            $label,
            $kitchen->name,
            $reason ? " — {$reason}" : ''
        );

        $dedupeBase = 'reassign:'.$group->id.':'.$eventType.':'.$kitchen->id.':'.now()->timestamp;
        $created = 0;

        foreach (self::opsAndAdminUserIds() as $userId) {
            $alert = self::createOnce(
                $userId,
                StaffAlert::TYPE_NEEDS_REASSIGNMENT,
                $title,
                $body,
                (int) $group->id,
                [
                    'event_type' => $eventType,
                    'kitchen_id' => $kitchen->id,
                    'reason' => $reason,
                ],
                $dedupeBase.':'.$userId
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    public static function notifyKitchenAcceptWindowClosing(OrderGroup $group, User $kitchen): ?StaffAlert
    {
        $group->loadMissing('menuItem');
        $closeAt = KitchenAcceptWindow::windowCloseAt($group);

        return self::createOnce(
            (int) $kitchen->id,
            StaffAlert::TYPE_ACCEPT_WINDOW_CLOSING,
            'Accept window closing soon',
            sprintf(
                '%s (%s) closes at %s. Accept soon if you can take it.',
                $group->name,
                $group->menuItem?->name ?? 'menu',
                $closeAt->format('g:i A')
            ),
            (int) $group->id,
            [
                'close_at' => $closeAt->toIso8601String(),
            ],
            'accept_warn:'.$group->id.':'.$kitchen->id.':'.$closeAt->toDateString()
        );
    }

    /**
     * Parcel call: kitchen dispatched a lunch order — notify riders serving that area.
     *
     * @return int number of alerts created
     */
    public static function notifyRidersLunchDispatch(Order $order): int
    {
        $order->loadMissing(['menuItem', 'orderGroup']);
        $areaId = DeliveryAreaScope::orderAreaId($order);
        $riders = DeliveryAreaScope::ridersForArea($areaId);
        if ($riders === []) {
            return 0;
        }

        $menu = $order->menuItem?->name ?? 'Order';
        $title = 'New lunch run #'.$order->id;
        $body = sprintf(
            '%s is packed and ready for pickup (qty %d).',
            $menu,
            (int) $order->quantity
        );
        $groupId = $order->orderGroup?->id;
        $created = 0;

        foreach ($riders as $rider) {
            $alert = self::createOnce(
                (int) $rider->id,
                StaffAlert::TYPE_LUNCH_DISPATCH,
                $title,
                $body,
                $groupId ? (int) $groupId : null,
                [
                    'order_id' => $order->id,
                    'area_id' => $areaId,
                    'run_type' => DeliveryRunType::KITCHEN_TO_CORPORATE,
                ],
                'lunch_dispatch:'.$order->id.':'.$rider->id
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    public static function unreadCount(int $userId): int
    {
        if (! self::tableReady()) {
            return 0;
        }

        return StaffAlert::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public static function markRead(int $alertId, int $userId): bool
    {
        $alert = StaffAlert::query()
            ->whereKey($alertId)
            ->where('user_id', $userId)
            ->first();

        if (! $alert) {
            return false;
        }

        $alert->markRead();

        return true;
    }

    public static function markAllRead(int $userId): int
    {
        return StaffAlert::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @return list<int>
     */
    public static function opsAndAdminUserIds(): array
    {
        $roleIds = Role::query()
            ->whereIn('name', ['admin', 'operation'])
            ->pluck('id')
            ->all();

        if ($roleIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('role_id', $roleIds)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected static function createOnce(
        int $userId,
        string $type,
        string $title,
        ?string $body,
        ?int $orderGroupId,
        ?array $meta,
        ?string $dedupeKey
    ): ?StaffAlert {
        if (! self::tableReady()) {
            return null;
        }

        if ($dedupeKey !== null) {
            $existing = StaffAlert::query()
                ->where('user_id', $userId)
                ->where('dedupe_key', $dedupeKey)
                ->first();
            if ($existing) {
                return null;
            }
        }

        try {
            return StaffAlert::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'order_group_id' => $orderGroupId,
                'meta' => $meta,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function tableReady(): bool
    {
        try {
            return Schema::hasTable('staff_alerts');
        } catch (\Throwable) {
            return false;
        }
    }
}
