<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OpsSlaBoard
{
    /**
     * Unassigned groups for today onward, with accept-window payloads.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function unassignedGroups(?Carbon $now = null, ?string $fromDate = null): Collection
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');
        $fromDate ??= $now->toDateString();

        $groups = OrderGroup::query()
            ->with([
                'menuItem',
                'orders' => fn ($q) => $q
                    ->with('area.city')
                    ->where('order_status', '!=', 'cancelled')
                    ->orderBy('delivery_time'),
            ])
            ->whereNull('kitchen_id')
            ->whereDate('delivery_date', '>=', $fromDate)
            ->whereHas('orders', fn ($q) => $q->where('order_status', '!=', 'cancelled'))
            ->orderBy('delivery_date')
            ->orderBy('name')
            ->get();

        $kitchenHints = self::kitchenCapacityHints();

        return $groups->map(function (OrderGroup $group) use ($now, $kitchenHints) {
            $window = KitchenAcceptWindow::statusPayload($group, $now);
            $qty = (int) $group->orders->sum('quantity');
            $area = $group->orders->first()?->area;

            return [
                'id' => $group->id,
                'name' => $group->name,
                'delivery_date' => $group->delivery_date?->toDateString(),
                'menu' => $group->menuItem?->name ?? '—',
                'order_count' => $group->orders->count(),
                'qty' => $qty,
                'area_name' => $area?->name,
                'city_name' => $area?->city?->name,
                'accept_window' => $window,
                'kitchen_hints' => $kitchenHints,
                'severity' => match ($window['state']) {
                    'closed' => 0,
                    'open' => $window['closing_soon'] ? 1 : 2,
                    default => 3,
                },
            ];
        })->sortBy([
            ['severity', 'asc'],
            ['delivery_date', 'asc'],
            ['name', 'asc'],
        ])->values();
    }

    /**
     * Top kitchens with remaining capacity for pool UI hints.
     *
     * @return list<array{id:int,name:string,remaining:int,tier:string}>
     */
    public static function kitchenCapacityHints(int $limit = 3): array
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $k) => [
                'id' => $k->id,
                'name' => $k->name,
                'remaining' => KitchenCapacity::remainingSlots($k),
                'tier' => KitchenTier::label(KitchenTier::normalize($k->kitchen_tier)),
            ])
            ->filter(fn (array $row) => $row['remaining'] > 0)
            ->sortByDesc('remaining')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Assigned orders past dispatch deadline and not yet packed/dispatched.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function lateToPack(?Carbon $now = null): Collection
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');

        $orders = Order::query()
            ->with(['menuItem', 'orderGroup.kitchen', 'user'])
            ->whereIn('order_status', ['processing', 'ready'])
            ->whereHas('orderGroup', fn ($q) => $q->whereNotNull('kitchen_id'))
            ->whereDate('delivery_date', '>=', $now->copy()->subDay()->toDateString())
            ->whereDate('delivery_date', '<=', $now->copy()->addDays(2)->toDateString())
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->get()
            ->filter(function (Order $order) use ($now) {
                return DispatchDeadline::forOrder($order)->lt($now);
            });

        return $orders->map(function (Order $order) use ($now) {
            $deadline = DispatchDeadline::forOrder($order);
            $minutesLate = (int) $deadline->diffInMinutes($now);

            return [
                'id' => $order->id,
                'order_status' => $order->order_status,
                'delivery_date' => $order->delivery_date?->toDateString(),
                'delivery_time' => $order->delivery_time,
                'menu' => $order->menuItem?->name ?? '—',
                'qty' => (int) $order->quantity,
                'kitchen_id' => $order->orderGroup?->kitchen_id,
                'kitchen' => $order->orderGroup?->kitchen?->name
                    ?? $order->orderGroup?->kitchenDisplayName()
                    ?? '—',
                'group_id' => $order->orderGroup?->id,
                'group_name' => $order->orderGroup?->name,
                'deadline_iso' => $deadline->toIso8601String(),
                'deadline_label' => $deadline->format('g:i A'),
                'minutes_late' => $minutesLate,
            ];
        })->values();
    }

    /**
     * @return array{unassigned_closed:int,unassigned_open:int,unassigned_total:int,late_to_pack:int}
     */
    public static function counts(?Carbon $now = null): array
    {
        $unassigned = self::unassignedGroups($now);
        $late = self::lateToPack($now);

        return [
            'unassigned_closed' => $unassigned->where('accept_window.state', 'closed')->count(),
            'unassigned_open' => $unassigned->where('accept_window.state', 'open')->count(),
            'unassigned_total' => $unassigned->count(),
            'late_to_pack' => $late->count(),
        ];
    }
}
