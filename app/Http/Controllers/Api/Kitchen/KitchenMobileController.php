<?php

namespace App\Http\Controllers\Api\Kitchen;

use App\Http\Controllers\Controller;
use App\Livewire\Kitchen\IncomingBoxes as IncomingBoxesUi;
use App\Models\Area;
use App\Models\CashHandover;
use App\Models\DeviceToken;
use App\Models\KitchenBoxRequest;
use App\Models\KitchenBoxRequestLog;
use App\Models\KitchenMiddoTransfer;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\OrderGroup;
use App\Models\OrderGroupEvent;
use App\Models\StaffAlert;
use App\Models\User;
use App\Models\UserLog;
use App\Support\CashHandoverActions;
use App\Support\KitchenAcceptWindow;
use App\Support\KitchenAccountLedger;
use App\Support\KitchenApiPresenter;
use App\Support\KitchenBoxRequestFlow;
use App\Support\KitchenBoxStock;
use App\Support\KitchenCapacity;
use App\Support\KitchenComplaints;
use App\Support\KitchenIngredientRollup;
use App\Support\KitchenMoneyService;
use App\Support\MiddoBoxKitchenActions;
use App\Support\MiddoBoxLifecycle;
use App\Support\MiddoSettings;
use App\Support\OrderGroupKitchenAssignment;
use App\Support\OrderKitchenActions;
use App\Support\OrderKitchenDispatch;
use App\Support\OrderMoneyFlow;
use App\Support\PayoutChannel;
use App\Support\StaffAlerts;
use App\Support\UserAudit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

        $lastMonthStart = Carbon::now('Asia/Dhaka')->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = Carbon::now('Asia/Dhaka')->subMonthNoOverflow()->endOfMonth()->toDateString();

        $tiles = [
            KitchenApiPresenter::dashboardTile('alerts', 'Alerts', StaffAlerts::unreadCount($kitchenId)),
            KitchenApiPresenter::dashboardTile(
                'active_orders',
                'My active orders',
                (clone $activeOrdersQuery)->count()
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
                'claimable_groups',
                'Middo order groups',
                $claimableCount
            ),
            KitchenApiPresenter::dashboardTile(
                'boxes_in_stock',
                'Boxes in Stock',
                KitchenBoxStock::sendableCount($kitchenId)
            ),
            KitchenApiPresenter::dashboardTile(
                'orders_this_month',
                'My Orders this month',
                OrderGroup::query()
                    ->where('kitchen_id', $kitchenId)
                    ->whereBetween('delivery_date', [$monthStart, $monthEnd])
                    ->count()
            ),
            KitchenApiPresenter::dashboardTile(
                'orders_last_month',
                'Last month',
                OrderGroup::query()
                    ->where('kitchen_id', $kitchenId)
                    ->whereBetween('delivery_date', [$lastMonthStart, $lastMonthEnd])
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

    public function shoppingList(Request $request): JsonResponse
    {
        $date = $request->query('date') ?: now('Asia/Dhaka')->toDateString();

        return response()->json(
            KitchenIngredientRollup::forKitchen((int) $request->user()->id, (string) $date)
        );
    }

    public function dispatchOptions(Request $request, int $id): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;
        $order = Order::query()
            ->with(['menuItem', 'orderGroup', 'deliveryRider', 'area'])
            ->whereKey($id)
            ->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $kitchenId))
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $canDispatch = $order->isRiderAssignedAwaitingDispatch();

        return response()->json([
            'order' => KitchenApiPresenter::order($order),
            'can_dispatch' => $canDispatch,
            'required_quantity' => (int) $order->quantity,
            'available_boxes' => $canDispatch
                ? OrderKitchenDispatch::availableBoxesForKitchen($kitchenId)
                : [],
            'message' => $canDispatch ? null : match (true) {
                $order->order_status === 'processing' => 'Mark this order ready first.',
                $order->order_status === 'ready' => 'Wait for ops to assign a rider before dispatching.',
                $order->dispatched_at !== null => 'This order has already been dispatched.',
                default => 'This order can no longer be dispatched.',
            },
        ]);
    }

    public function dispatchOrder(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'box_ids' => ['required', 'array', 'min:1'],
            'box_ids.*' => ['integer', 'distinct'],
        ]);

        try {
            $order = Order::query()->find($id);
            if (! $order) {
                return response()->json(['message' => 'Order not found.'], 404);
            }

            $fresh = OrderKitchenDispatch::dispatchWithBoxes(
                $order,
                (int) $request->user()->id,
                $data['box_ids']
            );

            return response()->json([
                'message' => "Order #{$fresh->id} packed and dispatched.",
                'order' => KitchenApiPresenter::order($fresh),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not pack this order.',
            ], 422);
        }
    }

    public function incomingBoxes(Request $request): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;

        $latestLogIds = MiddoBoxLog::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('middo_box_id')
            ->pluck('id');

        $visibleBoxIds = MiddoBoxLog::query()
            ->whereIn('id', $latestLogIds)
            ->whereIn('log_action', IncomingBoxesUi::LIST_ACTIONS)
            ->pluck('middo_box_id');

        $boxes = MiddoBox::query()
            ->with(['logs' => fn ($q) => $q->latest('id')->limit(1)])
            ->incomingToKitchen($kitchenId)
            ->whereIn('id', $visibleBoxIds)
            ->orderBy('qr_code_id')
            ->paginate(20);

        $nodes = collect($boxes->items())->map(function (MiddoBox $box) {
            $latestAction = $box->logs->first()?->log_action;

            return array_merge(KitchenApiPresenter::box($box), [
                'latest_action' => $latestAction,
                'can_receive' => in_array($latestAction, IncomingBoxesUi::RECEIVE_ACTIONS, true),
            ]);
        })->values()->all();

        return response()->json([
            'boxes' => $nodes,
            'meta' => KitchenApiPresenter::paginationMeta($boxes),
        ]);
    }

    public function receiveBox(Request $request, int $id): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;

        try {
            $qr = DB::transaction(function () use ($id, $kitchenId) {
                $box = MiddoBox::query()->whereKey($id)->lockForUpdate()->first();

                if (! $box || ! $box->isIncomingToKitchen($kitchenId)) {
                    throw new \RuntimeException('This box is not incoming to your kitchen.');
                }

                $latestAction = KitchenBoxRequestFlow::latestBoxAction($box->id);
                if (! in_array($latestAction, IncomingBoxesUi::RECEIVE_ACTIONS, true)) {
                    throw new \RuntimeException('Wait for the rider to hand this box before confirming receive.');
                }

                $box->update([
                    'held_by_user_id' => $kitchenId,
                    'kitchen_id' => $kitchenId,
                    'asset_status' => 'active',
                    'last_scanned_at' => now(),
                ]);

                MiddoBoxLog::create([
                    'middo_box_id' => $box->id,
                    'custody_status' => 'assigned_at_kitchen',
                    'log_action' => 'received_at_kitchen',
                    'notes' => 'Received at '.(MiddoBoxLifecycle::partyLabel(User::query()->find($kitchenId)) ?: 'kitchen'),
                    'performed_by' => $kitchenId,
                ]);

                KitchenBoxRequestFlow::markReceivedAtKitchen($box, $kitchenId);

                return $box->qr_code_id;
            });

            return response()->json([
                'message' => "Received {$qr} into kitchen inventory.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not receive this box.',
            ], 422);
        }
    }

    public function requestBoxes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $kitchen */
        $kitchen = $request->user();

        $boxRequest = KitchenBoxRequest::create([
            'kitchen_id' => $kitchen->id,
            'quantity' => (int) $data['quantity'],
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
            'requested_by' => $kitchen->id,
        ]);

        KitchenBoxRequestFlow::logRequestEvent(
            $boxRequest,
            KitchenBoxRequestLog::EVENT_REQUESTED,
            $kitchen->id,
            $boxRequest->note,
            ['quantity' => (int) $boxRequest->quantity]
        );

        StaffAlerts::notifyOpsKitchenBoxRequest($boxRequest);

        return response()->json([
            'message' => "Requested {$boxRequest->quantity} Middo ".str('box')->plural($boxRequest->quantity).'.',
            'request' => [
                'id' => $boxRequest->id,
                'quantity' => (int) $boxRequest->quantity,
                'status' => $boxRequest->status,
                'note' => $boxRequest->note,
            ],
        ], 201);
    }

    public function cancelBoxRequest(Request $request, int $id): JsonResponse
    {
        $boxRequest = KitchenBoxRequest::query()
            ->whereKey($id)
            ->where('kitchen_id', $request->user()->id)
            ->where('status', KitchenBoxRequest::STATUS_PENDING)
            ->first();

        if (! $boxRequest) {
            return response()->json(['message' => 'That box request is no longer pending.'], 404);
        }

        try {
            KitchenBoxRequestFlow::cancelRequest($boxRequest, (int) $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Box request cancelled.']);
    }

    public function markBoxDamaged(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $box = MiddoBox::query()->findOrFail($id);
            $damaged = MiddoBoxKitchenActions::markDamaged($box, (int) $request->user()->id, $data['notes'] ?? null);

            return response()->json([
                'message' => "{$damaged->qr_code_id} marked damaged.",
                'box' => KitchenApiPresenter::box($damaged),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not mark box damaged.',
            ], 422);
        }
    }

    public function sendBoxToWarehouse(Request $request, int $id): JsonResponse
    {
        try {
            $box = MiddoBox::query()->findOrFail($id);
            $sent = MiddoBoxKitchenActions::sendToWarehouse($box, (int) $request->user()->id);
            $message = MiddoSettings::kitchenToOpsViaRider()
                ? "{$sent->qr_code_id} marked ready to ship. Ops will assign a rider."
                : "{$sent->qr_code_id} sent to Middo warehouse.";

            return response()->json([
                'message' => $message,
                'box' => KitchenApiPresenter::box($sent),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not send box to warehouse.',
            ], 422);
        }
    }

    public function account(Request $request): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;
        $balance = KitchenAccountLedger::balance($kitchenId);

        return response()->json([
            'balance' => $balance,
            'receivable' => max(0, $balance),
            'payable_to_middo' => $balance < 0 ? abs($balance) : 0,
            'preferred_payout_channel' => $request->user()->preferredPayoutChannel(),
            'has_complete_payout_method' => $request->user()->hasCompletePayoutMethod(
                $request->user()->preferredPayoutChannel()
            ),
        ]);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'payout_channel' => ['nullable', 'in:'.implode(',', PayoutChannel::partnerChannels())],
        ]);

        /** @var User $user */
        $user = $request->user();
        $kitchenId = (int) $user->id;
        $channel = $data['payout_channel'] ?? $user->preferredPayoutChannel() ?? PayoutChannel::defaultPartnerChannel();

        try {
            if (! $user->hasCompletePayoutMethod($channel)) {
                throw new \RuntimeException(
                    'Add your '.PayoutChannel::label($channel).' details in profile before requesting this payout.'
                );
            }

            $details = $user->payoutDetailsFor($channel);
            PayoutChannel::assertValid($channel, $details);

            $amount = KitchenAccountLedger::balance($kitchenId);
            if ($amount < 1) {
                throw new \RuntimeException('Nothing to withdraw — Middo does not currently owe you.');
            }

            $withdrawal = KitchenMoneyService::requestWithdrawal(
                $kitchenId,
                $amount,
                $channel,
                $details,
                $data['notes'] ?? null,
                $kitchenId,
            );

            return response()->json([
                'message' => 'Withdrawal request submitted. Receivable reduced; waiting for Middo approval.',
                'withdrawal' => [
                    'id' => $withdrawal->id,
                    'amount' => (int) $withdrawal->amount,
                    'status' => $withdrawal->status,
                    'payout_channel' => $withdrawal->payout_channel ?? $channel,
                ],
                'balance' => KitchenAccountLedger::balance($kitchenId),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not submit withdrawal.',
            ], 422);
        }
    }

    public function transferToMiddo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'proof' => ['required', 'image', 'max:4096'],
        ]);

        $kitchenId = (int) $request->user()->id;

        $transfer = KitchenMiddoTransfer::create([
            'kitchen_user_id' => $kitchenId,
            'amount' => (int) $data['amount'],
            'status' => KitchenMiddoTransfer::STATUS_PENDING,
            'reference_code' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $relativePath = 'img/kitchen-transfers';
        $directory = public_path($relativePath);
        File::ensureDirectoryExists($directory);

        $extension = strtolower($request->file('proof')->extension() ?: 'jpg');
        $filename = "transfer-{$transfer->id}.{$extension}";
        $request->file('proof')->move($directory, $filename);

        $transfer->update([
            'proof_path' => $relativePath.'/'.$filename,
        ]);

        return response()->json([
            'message' => 'Transfer submitted with proof. Waiting for Middo confirmation.',
            'transfer' => [
                'id' => $transfer->id,
                'amount' => (int) $transfer->amount,
                'status' => $transfer->status,
                'proof_path' => $transfer->proof_path,
            ],
        ], 201);
    }

    public function cashHandovers(Request $request): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;

        $scopedIds = CashHandover::query()
            ->with(['items.order.orderGroup'])
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('target', CashHandover::TARGET_KITCHEN)
                    ->orWhereNull('target');
            })
            ->get()
            ->filter(fn (CashHandover $h) => $this->handoverBelongsToKitchen($h, $kitchenId))
            ->pluck('id')
            ->all();

        $handovers = CashHandover::query()
            ->with(['rider', 'items.order'])
            ->whereIn('id', $scopedIds ?: [0])
            ->orderBy('id')
            ->paginate(20);

        return response()->json([
            'wallet_balance' => KitchenAccountLedger::balance($kitchenId),
            'handovers' => collect($handovers->items())->map(fn (CashHandover $h) => [
                'id' => $h->id,
                'amount' => (int) $h->amount,
                'status' => $h->status,
                'rider_name' => $h->rider?->name,
                'rider_mobile' => $h->rider?->mobile,
                'item_count' => $h->items->count(),
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => KitchenApiPresenter::paginationMeta($handovers),
        ]);
    }

    public function acceptCashHandover(Request $request, int $id): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;

        try {
            DB::transaction(function () use ($id, $kitchenId) {
                $handover = CashHandover::query()
                    ->with('items.order.orderGroup')
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (! $handover || ! $handover->isPending()) {
                    throw new \RuntimeException('This cash handover is no longer pending.');
                }

                if (! $handover->isKitchenTarget()) {
                    throw new \RuntimeException('This handover is for Middo/ops, not kitchen.');
                }

                if (! $this->handoverBelongsToKitchen($handover, $kitchenId)) {
                    throw new \RuntimeException('This cash handover is not linked to your kitchen’s orders.');
                }

                $rider = User::query()->whereKey($handover->rider_id)->lockForUpdate()->firstOrFail();

                if ((int) $rider->balance < (int) $handover->amount) {
                    throw new \RuntimeException('Rider balance is insufficient for this handover.');
                }

                $rider->decrement('balance', (int) $handover->amount);

                KitchenAccountLedger::debit(
                    $kitchenId,
                    (int) $handover->amount,
                    'cash_received',
                    CashHandover::class,
                    $handover->id,
                    "Cash handover #{$handover->id} from rider #{$rider->id}",
                    $kitchenId,
                );

                $handover->update([
                    'status' => 'accepted',
                    'accepted_by' => $kitchenId,
                    'accepted_at' => now(),
                ]);

                OrderMoneyFlow::recordCashHandover($handover->fresh(['items.order.orderGroup']), $kitchenId);
            });

            $balance = KitchenAccountLedger::balance($kitchenId);

            return response()->json([
                'message' => $balance < 0
                    ? "Cash handover #{$id} accepted. You now owe Middo ৳".number_format(abs($balance)).'.'
                    : "Cash handover #{$id} accepted. Kitchen wallet balance ৳".number_format($balance).'.',
                'wallet_balance' => $balance,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not accept cash handover.',
            ], 422);
        }
    }

    public function rejectCashHandover(Request $request, int $id): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;

        try {
            $handover = CashHandover::query()
                ->with('items.order.orderGroup')
                ->whereKey($id)
                ->first();

            if (! $handover || ! $handover->isPending()) {
                throw new \RuntimeException('This cash handover is no longer pending.');
            }

            if (! $handover->isKitchenTarget()) {
                throw new \RuntimeException('This handover is for Middo/ops, not kitchen.');
            }

            if (! $this->handoverBelongsToKitchen($handover, $kitchenId)) {
                throw new \RuntimeException('This cash handover is not linked to your kitchen’s orders.');
            }

            CashHandoverActions::reject($handover, $kitchenId, 'Rejected by kitchen');

            return response()->json([
                'message' => "Cash handover #{$id} rejected. Rider can re-submit those orders.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not reject cash handover.',
            ], 422);
        }
    }

    public function complaints(Request $request): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;
        $category = $request->query('category');

        $query = KitchenComplaints::scopedRootsQuery($kitchenId)
            ->with(['order.menuItem:id,name'])
            ->latest('id');

        if (filled($category)) {
            $query->where('category', $category);
        }

        $complaints = $query->paginate(20);

        return response()->json([
            'complaints' => collect($complaints->items())->map(fn (OrderComplaint $c) => [
                'id' => $c->id,
                'category' => $c->category,
                'category_label' => KitchenComplaints::categoryLabel($c->category),
                'message' => $c->message,
                'status' => $c->status,
                'order_id' => $c->order_id,
                'menu_name' => $c->order?->menuItem?->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => KitchenApiPresenter::paginationMeta($complaints),
        ]);
    }

    public function showComplaint(Request $request, int $id): JsonResponse
    {
        $kitchenId = (int) $request->user()->id;
        $complaint = OrderComplaint::query()->with(['order.menuItem', 'createdBy:id,first_name,last_name'])->find($id);

        if (! $complaint) {
            return response()->json(['message' => 'Complaint not found.'], 404);
        }

        $root = $complaint->parent_id
            ? OrderComplaint::query()->find($complaint->parent_id)
            : $complaint;

        if (! $root || ! KitchenComplaints::belongsToKitchen($root, $kitchenId)) {
            return response()->json(['message' => 'Complaint not found.'], 404);
        }

        $thread = $root->threadMessages()->map(fn (OrderComplaint $m) => [
            'id' => $m->id,
            'message' => $m->message,
            'created_by_name' => $m->createdBy?->name,
            'created_at' => $m->created_at?->toIso8601String(),
            'is_root' => $m->parent_id === null,
        ])->values()->all();

        return response()->json([
            'complaint' => [
                'id' => $root->id,
                'category' => $root->category,
                'category_label' => KitchenComplaints::categoryLabel($root->category),
                'status' => $root->status,
                'order_id' => $root->order_id,
                'menu_name' => $root->order?->menuItem?->name,
                'thread' => $thread,
            ],
        ]);
    }

    protected function handoverBelongsToKitchen(CashHandover $handover, int $kitchenId): bool
    {
        $handover->loadMissing('items.order.orderGroup');

        if ($handover->items->isEmpty()) {
            return false;
        }

        return $handover->items->every(function ($item) use ($kitchenId) {
            return (int) ($item->order?->orderGroup?->kitchen_id) === $kitchenId;
        });
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
