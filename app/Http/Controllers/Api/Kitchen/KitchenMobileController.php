<?php

namespace App\Http\Controllers\Api\Kitchen;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\DeviceToken;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupEvent;
use App\Models\StaffAlert;
use App\Models\User;
use App\Models\UserLog;
use App\Support\KitchenAcceptWindow;
use App\Support\KitchenApiPresenter;
use App\Support\KitchenBoxStock;
use App\Support\KitchenCapacity;
use App\Support\KitchenComplaints;
use App\Support\OrderGroupKitchenAssignment;
use App\Support\OrderKitchenActions;
use App\Support\StaffAlerts;
use App\Support\UserAudit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class KitchenMobileController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'mobile' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User|null $user */
        $user = User::query()
            ->with(['role', 'area', 'city'])
            ->where('mobile', $credentials['mobile'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGIN_FAILED,
                source: UserAudit::SOURCE_KITCHEN_MOBILE,
                performedBy: $user?->id,
                metadata: [
                    'mobile' => $credentials['mobile'],
                    'device_name' => $credentials['device_name'] ?? null,
                ],
            );

            throw ValidationException::withMessages([
                'mobile' => ['Invalid mobile number or password.'],
            ]);
        }

        if ($user->status !== 'active') {
            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGIN_BLOCKED,
                source: UserAudit::SOURCE_KITCHEN_MOBILE,
                performedBy: $user->id,
                metadata: [
                    'reason' => 'inactive',
                    'status' => $user->status,
                    'device_name' => $credentials['device_name'] ?? null,
                ],
            );

            return response()->json([
                'message' => 'Account is not active.',
                'status' => $user->status,
            ], 403);
        }

        if ($user->role?->name !== 'kitchen') {
            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGIN_BLOCKED,
                source: UserAudit::SOURCE_KITCHEN_MOBILE,
                performedBy: $user->id,
                metadata: [
                    'reason' => 'wrong_role',
                    'role' => $user->role?->name,
                    'device_name' => $credentials['device_name'] ?? null,
                ],
            );

            return response()->json([
                'message' => 'Login as Kitchen to continue.',
            ], 403);
        }

        $token = $user->createToken(
            $credentials['device_name'] ?? 'middo-kitchen-mobile'
        )->plainTextToken;

        UserAudit::record(
            user: $user,
            event: UserLog::EVENT_LOGIN,
            source: UserAudit::SOURCE_KITCHEN_MOBILE,
            performedBy: $user->id,
            metadata: [
                'device_name' => $credentials['device_name'] ?? 'middo-kitchen-mobile',
            ],
        );

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => KitchenApiPresenter::user($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->user()?->currentAccessToken()?->delete();

        if ($user instanceof User) {
            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGOUT,
                source: UserAudit::SOURCE_KITCHEN_MOBILE,
                performedBy: $user->id,
            );
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => KitchenApiPresenter::user($request->user()),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:255'],
            'last_name' => ['required', 'string', 'min:2', 'max:255'],
            'mobile' => [
                'required',
                'string',
                'regex:/^01[3-9]\d{8}$/',
                'unique:users,mobile,'.$user->id,
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:users,email,'.$user->id,
            ],
            'address' => ['nullable', 'string', 'max:1000'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
        ]);

        $this->assertAreaBelongsToCity((int) $data['city_id'], (int) $data['area_id']);

        $user->first_name = $data['first_name'];
        $user->last_name = $data['last_name'];
        $user->mobile = $data['mobile'];
        $user->email = $data['email'] ?? null;
        $user->address = $data['address'] ?? null;
        $user->city_id = $data['city_id'];
        $user->area_id = $data['area_id'];
        $user->save();

        $user->load(['role', 'area', 'city']);

        return response()->json([
            'message' => 'Profile updated.',
            'user' => KitchenApiPresenter::user($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'The new password confirmation does not match.',
            'password.min' => 'The new password must be at least 8 characters.',
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function registerDeviceToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $token = DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? 'android',
                'device_name' => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Device token registered.',
            'id' => $token->id,
        ]);
    }

    public function unregisterDeviceToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
        ]);

        DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['message' => 'Device token removed.']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $kitchen */
        $kitchen = $request->user();
        $kitchenId = (int) $kitchen->id;
        $today = now('Asia/Dhaka')->toDateString();
        $monthStart = Carbon::now('Asia/Dhaka')->startOfMonth()->toDateString();
        $monthEnd = Carbon::now('Asia/Dhaka')->endOfMonth()->toDateString();
        $threeMonthsStart = Carbon::now('Asia/Dhaka')->subMonths(2)->startOfMonth()->toDateString();

        $activeOrdersQuery = OrderGroup::query()
            ->where('kitchen_id', $kitchenId)
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders', fn ($query) => $query->active());

        $claimableCount = OrderGroup::query()
            ->whereNull('kitchen_id')
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders')
            ->count();

        $tiles = [
            KitchenApiPresenter::dashboardTile('alerts', 'Alerts', StaffAlerts::unreadCount($kitchenId)),
            KitchenApiPresenter::dashboardTile(
                'complaints',
                'Complaints',
                KitchenComplaints::scopedRootsQuery($kitchenId)->count()
            ),
            KitchenApiPresenter::dashboardTile(
                'orders_this_month',
                'My Order this month',
                OrderGroup::query()
                    ->where('kitchen_id', $kitchenId)
                    ->whereBetween('delivery_date', [$monthStart, $monthEnd])
                    ->count()
            ),
            KitchenApiPresenter::dashboardTile(
                'orders_last_three_months',
                'Last 3 months',
                OrderGroup::query()
                    ->where('kitchen_id', $kitchenId)
                    ->whereBetween('delivery_date', [$threeMonthsStart, $monthEnd])
                    ->count()
            ),
            KitchenApiPresenter::dashboardTile(
                'preparing',
                'Preparing',
                (clone $activeOrdersQuery)
                    ->whereHas('orders', fn ($q) => $q->where('order_status', 'processing'))
                    ->count()
            ),
            KitchenApiPresenter::dashboardTile(
                'ready_for_pickup',
                'Ready for pickup',
                (clone $activeOrdersQuery)
                    ->whereHas('orders', fn ($q) => $q->where('order_status', 'ready'))
                    ->count()
            ),
            KitchenApiPresenter::dashboardTile(
                'active_orders',
                'My active orders',
                (clone $activeOrdersQuery)->count()
            ),
            KitchenApiPresenter::dashboardTile(
                'claimable_groups',
                'Middo order groups',
                $claimableCount
            ),
        ];

        return response()->json([
            'tiles' => $tiles,
            'insufficient_box_stock' => KitchenBoxStock::hasInsufficientStockVsAllowed($kitchen),
            'ops_incoming_notices' => KitchenBoxStock::opsIncomingNotices($kitchenId),
            'capacity' => [
                'open_groups' => KitchenCapacity::openGroupCount($kitchenId),
                'allowed_open_groups' => KitchenCapacity::effectiveAllowedOpenGroups($kitchen),
                'remaining_slots' => KitchenCapacity::remainingSlots($kitchen),
                'sendable_boxes' => KitchenBoxStock::sendableCount($kitchenId),
                'remaining_box_capacity' => KitchenBoxStock::remainingPlateCapacity($kitchenId),
            ],
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $alerts = StaffAlert::query()
            ->where('user_id', $userId)
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'unread_count' => StaffAlerts::unreadCount($userId),
            'alerts' => collect($alerts->items())->map(fn (StaffAlert $a) => KitchenApiPresenter::alert($a))->values()->all(),
            'meta' => KitchenApiPresenter::paginationMeta($alerts),
        ]);
    }

    public function markAlertRead(Request $request, int $id): JsonResponse
    {
        $ok = StaffAlerts::markRead($id, (int) $request->user()->id);

        if (! $ok) {
            return response()->json(['message' => 'Alert not found.'], 404);
        }

        return response()->json(['message' => 'Alert marked as read.']);
    }

    public function markAllAlertsRead(Request $request): JsonResponse
    {
        $count = StaffAlerts::markAllRead((int) $request->user()->id);

        return response()->json([
            'message' => $count > 0
                ? "Marked {$count} alert(s) as read."
                : 'No unread alerts.',
            'count' => $count,
        ]);
    }

    public function orderGroups(Request $request): JsonResponse
    {
        /** @var User $kitchen */
        $kitchen = $request->user();
        $kitchenId = (int) $kitchen->id;
        $today = now('Asia/Dhaka')->toDateString();
        $declinedIds = OrderGroupKitchenAssignment::declinedGroupIdsForKitchenToday($kitchenId);

        $remainingSlots = KitchenCapacity::remainingSlots($kitchen);

        $groups = OrderGroup::with([
            'menuItem',
            'area',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'area', 'packageSubscription.package'])
                ->where('order_status', '!=', 'cancelled')
                ->orderBy('delivery_time'),
            'events' => fn ($query) => $query
                ->whereIn('type', [OrderGroupEvent::TYPE_SHORTAGE, OrderGroupEvent::TYPE_DECLINE])
                ->latest()
                ->limit(3),
        ])
            ->whereNull('kitchen_id')
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders', fn ($query) => $query->where('order_status', '!=', 'cancelled'))
            ->when($declinedIds !== [], fn ($q) => $q->whereNotIn('id', $declinedIds))
            ->orderBy('delivery_date')
            ->orderBy('name')
            ->paginate(20);

        $nodes = collect(KitchenApiPresenter::orderGroups($groups->getCollection()))
            ->map(function (array $node) use ($groups, $kitchen, $remainingSlots) {
                /** @var OrderGroup|null $group */
                $group = $groups->getCollection()->firstWhere('id', $node['id']);
                $window = $group
                    ? KitchenAcceptWindow::statusPayload($group)
                    : ['is_open' => false, 'state' => 'closed', 'label' => '—', 'open_at_iso' => '', 'close_at_iso' => ''];

                $recentShortage = $group?->events
                    ?->first(fn (OrderGroupEvent $e) => $e->type === OrderGroupEvent::TYPE_SHORTAGE);

                $fitsBoxes = $group
                    ? KitchenBoxStock::canAcceptGroup($kitchen, $group)
                    : false;

                return array_merge($node, [
                    'accept_window' => $window,
                    'can_accept' => ($window['is_open'] ?? false) && $remainingSlots > 0 && $fitsBoxes,
                    'needs_more_boxes' => ($window['is_open'] ?? false) && $remainingSlots > 0 && ! $fitsBoxes,
                    'had_shortage' => $recentShortage !== null,
                    'shortage_reason' => $recentShortage?->reason,
                ]);
            })
            ->values()
            ->all();

        return response()->json([
            'groups' => $nodes,
            'capacity' => [
                'open_groups' => KitchenCapacity::openGroupCount($kitchenId),
                'allowed_open_groups' => KitchenCapacity::effectiveAllowedOpenGroups($kitchen),
                'remaining_slots' => $remainingSlots,
                'sendable_boxes' => KitchenBoxStock::sendableCount($kitchenId),
                'remaining_box_capacity' => KitchenBoxStock::remainingPlateCapacity($kitchenId),
                'insufficient_box_stock' => KitchenBoxStock::hasInsufficientStockVsAllowed($kitchen),
                'at_capacity' => $remainingSlots <= 0,
            ],
            'meta' => KitchenApiPresenter::paginationMeta($groups),
        ]);
    }

    public function acceptOrderGroup(Request $request, int $id): JsonResponse
    {
        try {
            $group = OrderGroup::query()->findOrFail($id);
            $accepted = OrderGroupKitchenAssignment::accept($group, $request->user());

            return response()->json([
                'message' => "Accepted {$accepted->name}. It is now assigned to your kitchen.",
                'group' => KitchenApiPresenter::orderGroups(
                    collect([$accepted->load(['menuItem', 'area', 'orders.menuItem', 'orders.area', 'orders.packageSubscription.package'])])
                )[0] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not accept this order group.',
            ], 422);
        }
    }

    public function declineOrderGroup(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            $group = OrderGroup::query()->findOrFail($id);
            OrderGroupKitchenAssignment::decline($group, $request->user(), $data['reason']);

            return response()->json([
                'message' => "Declined {$group->name}. It stays in the Middo pool for other kitchens.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not decline this order group.',
            ], 422);
        }
    }

    public function releaseOrderGroup(Request $request, int $id): JsonResponse
    {
        try {
            $group = OrderGroup::query()->findOrFail($id);
            OrderGroupKitchenAssignment::release($group, $request->user());

            return response()->json([
                'message' => "Released {$group->name} back to the Middo pool.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not release this group.',
            ], 422);
        }
    }

    public function reportShortage(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            $group = OrderGroup::query()->findOrFail($id);
            OrderGroupKitchenAssignment::reportShortage($group, $request->user(), $data['reason']);

            return response()->json([
                'message' => "Shortage reported for {$group->name}. Group returned to Middo pool.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not report shortage.',
            ], 422);
        }
    }

    public function markGroupReady(Request $request, int $id): JsonResponse
    {
        /** @var User $kitchen */
        $kitchen = $request->user();
        $kitchenId = (int) $kitchen->id;

        try {
            $group = OrderGroup::query()
                ->with('orders')
                ->whereKey($id)
                ->first();

            if (! $group || (int) $group->kitchen_id !== $kitchenId) {
                return response()->json(['message' => 'Order group not found for your kitchen.'], 404);
            }

            $marked = 0;
            $preAssigned = 0;
            foreach ($group->orders as $order) {
                if ($order->order_status !== 'processing' || $order->dispatched_at !== null) {
                    continue;
                }
                $fresh = OrderKitchenActions::markReady($order, $kitchen);
                $marked++;
                if ($fresh->order_status === 'rider_assigned') {
                    $preAssigned++;
                }
            }

            if ($marked === 0) {
                return response()->json([
                    'message' => 'No processing orders left to mark ready in this group.',
                ], 422);
            }

            $waitingOps = $marked - $preAssigned;
            $message = match (true) {
                $waitingOps === 0 => "Marked {$marked} order(s) ready — riders already assigned.",
                $preAssigned > 0 => "Marked {$marked} order(s) ready — {$waitingOps} still need ops to assign a rider.",
                default => "Marked {$marked} order(s) ready — ops will assign riders.",
            };

            return response()->json([
                'message' => $message,
                'marked' => $marked,
                'pre_assigned' => $preAssigned,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not mark group ready.',
            ], 422);
        }
    }

    public function activeOrders(Request $request): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;
        $today = now('Asia/Dhaka')->toDateString();

        $groups = OrderGroup::with([
            'menuItem',
            'area',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'area', 'deliveryRider', 'packageSubscription.package'])
                ->active()
                ->orderBy('delivery_time'),
        ])
            ->where('kitchen_id', $kitchenId)
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders', fn ($query) => $query->active())
            ->orderBy('delivery_date')
            ->orderBy('name')
            ->paginate(20);

        return response()->json([
            'groups' => KitchenApiPresenter::orderGroups($groups->getCollection()),
            'box_inventory_count' => MiddoBox::query()->sendableAtKitchen($kitchenId)->count(),
            'meta' => KitchenApiPresenter::paginationMeta($groups),
        ]);
    }

    public function showOrder(Request $request, int $id): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;

        $order = Order::query()
            ->with(['menuItem', 'area', 'deliveryRider', 'orderGroup.area', 'packageSubscription.package'])
            ->whereKey($id)
            ->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $kitchenId))
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json([
            'order' => KitchenApiPresenter::order($order),
        ]);
    }

    public function markOrderReady(Request $request, int $id): JsonResponse
    {
        try {
            $order = Order::query()->find($id);
            if (! $order) {
                return response()->json(['message' => 'Order not found.'], 404);
            }

            $kitchenId = (int) $request->user()->id;
            $order->loadMissing('orderGroup');
            if ((int) ($order->orderGroup?->kitchen_id) !== $kitchenId) {
                return response()->json(['message' => 'Order not found for your kitchen.'], 404);
            }

            $fresh = OrderKitchenActions::markReady($order, $request->user());
            $fresh->load(['menuItem', 'area', 'deliveryRider', 'orderGroup.area', 'packageSubscription.package']);

            $riderName = $fresh->deliveryRider?->name;
            $message = $fresh->order_status === 'rider_assigned' && $riderName
                ? "Order #{$fresh->id} marked ready — assigned to {$riderName}."
                : "Order #{$fresh->id} marked ready — ops will assign a rider.";

            return response()->json([
                'message' => $message,
                'order' => KitchenApiPresenter::order($fresh),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not mark order ready.',
            ], 422);
        }
    }

    public function menusToday(Request $request): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;
        $deliveryDate = $request->query('date') ?: now('Asia/Dhaka')->toDateString();

        $rows = Order::query()
            ->with('menuItem')
            ->whereDate('delivery_date', $deliveryDate)
            ->whereIn('order_status', Order::ACTIVE_STATUSES)
            ->where(function ($query) use ($kitchenId) {
                $query->whereHas('orderGroup', function ($groupQuery) use ($kitchenId) {
                    $groupQuery->where('kitchen_id', $kitchenId)
                        ->orWhereNull('kitchen_id');
                })->orWhereDoesntHave('orderGroup');
            })
            ->selectRaw('menu_item_id, SUM(quantity) as total_qty, COUNT(*) as order_count')
            ->groupBy('menu_item_id')
            ->get();

        $menus = $rows->map(function ($row) {
            $item = $row->menuItem;
            if (! $item) {
                return null;
            }

            return array_merge(KitchenApiPresenter::menuItem($item), [
                'total_qty' => (int) $row->total_qty,
                'order_count' => (int) $row->order_count,
            ]);
        })->filter()->values()->all();

        return response()->json([
            'delivery_date' => $deliveryDate,
            'menus' => $menus,
        ]);
    }

    public function showMenu(Request $request, int $id): JsonResponse
    {
        $item = MenuItem::query()->find($id);
        if (! $item) {
            return response()->json(['message' => 'Menu not found.'], 404);
        }

        return response()->json([
            'menu' => KitchenApiPresenter::menuItem($item),
        ]);
    }

    public function boxesAtKitchen(Request $request): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;

        $boxes = MiddoBox::query()
            ->sendableAtKitchen($kitchenId)
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'boxes' => collect($boxes->items())->map(fn (MiddoBox $b) => KitchenApiPresenter::box($b))->values()->all(),
            'count' => $boxes->total(),
            'meta' => KitchenApiPresenter::paginationMeta($boxes),
        ]);
    }

    protected function assertAreaBelongsToCity(int $cityId, int $areaId): void
    {
        $ok = Area::query()
            ->whereKey($areaId)
            ->where('city_id', $cityId)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'area_id' => ['Selected area does not belong to the selected city.'],
            ]);
        }
    }
}
