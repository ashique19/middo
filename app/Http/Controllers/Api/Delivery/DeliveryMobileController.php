<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Models\CashHandover;
use App\Models\CustomRun;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\StaffAlert;
use App\Models\User;
use App\Models\UserLog;
use App\Support\DeliveryApiPresenter;
use App\Support\DeliveryAreaScope;
use App\Support\DeliveryMobileActions;
use App\Support\PayoutChannel;
use App\Support\RiderPendingBoxes;
use App\Support\RiderShift;
use App\Support\StaffAlerts;
use App\Support\UserAudit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeliveryMobileController extends Controller
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
                source: UserAudit::SOURCE_DELIVERY_MOBILE,
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
                source: UserAudit::SOURCE_DELIVERY_MOBILE,
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

        if ($user->role?->name !== 'delivery') {
            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGIN_BLOCKED,
                source: UserAudit::SOURCE_DELIVERY_MOBILE,
                performedBy: $user->id,
                metadata: [
                    'reason' => 'wrong_role',
                    'role' => $user->role?->name,
                    'device_name' => $credentials['device_name'] ?? null,
                ],
            );

            return response()->json([
                'message' => 'Login as Delivery to continue.',
            ], 403);
        }

        $token = $user->createToken(
            $credentials['device_name'] ?? 'middo-delivery-mobile'
        )->plainTextToken;

        UserAudit::record(
            user: $user,
            event: UserLog::EVENT_LOGIN,
            source: UserAudit::SOURCE_DELIVERY_MOBILE,
            performedBy: $user->id,
            metadata: [
                'device_name' => $credentials['device_name'] ?? 'middo-delivery-mobile',
            ],
        );

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => DeliveryApiPresenter::user($user),
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
                source: UserAudit::SOURCE_DELIVERY_MOBILE,
                performedBy: $user->id,
            );
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => DeliveryApiPresenter::user($user),
            'shift_status' => $user->riderShiftStatus(),
            'can_accept_new_runs' => $user->canAcceptNewRuns(),
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
        /** @var User $rider */
        $rider = $request->user();
        $riderId = (int) $rider->id;
        $shift = $rider->riderShiftStatus();

        $tiles = [
            DeliveryApiPresenter::dashboardTile('alerts', 'Alerts', StaffAlerts::unreadCount($riderId)),
            DeliveryApiPresenter::dashboardTile(
                'runs',
                'Kitchen dispatches',
                Order::query()
                    ->kitchenDispatched()
                    ->tap(fn ($q) => DeliveryAreaScope::applyKitchenDispatchedVisibleToRider($q, $rider))
                    ->count()
            ),
            DeliveryApiPresenter::dashboardTile(
                'custom_runs',
                'Custom runs',
                CustomRun::query()
                    ->visibleToRider($rider)
                    ->whereIn('status', [CustomRun::STATUS_PENDING, CustomRun::STATUS_STARTED])
                    ->count()
            ),
            DeliveryApiPresenter::dashboardTile(
                'boxes',
                'Middo boxes pending',
                RiderPendingBoxes::countForRider($riderId)
            ),
            DeliveryApiPresenter::dashboardTile(
                'delivered',
                'Delivered orders',
                Order::query()->deliveredForRider($riderId)->count()
            ),
            DeliveryApiPresenter::dashboardTile(
                'cash',
                'Cash on hand',
                (int) $rider->balance
            ),
        ];

        return response()->json([
            'tiles' => $tiles,
            'shift_status' => $shift,
            'shift_label' => RiderShift::label($shift),
            'shift_options' => [
                RiderShift::ON => RiderShift::label(RiderShift::ON),
                RiderShift::OFF => RiderShift::label(RiderShift::OFF),
                RiderShift::UNABLE => RiderShift::label(RiderShift::UNABLE),
            ],
            'can_accept_new_runs' => $rider->canAcceptNewRuns(),
        ]);
    }

    public function setShift(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', RiderShift::all())],
        ]);

        try {
            $rider = DeliveryMobileActions::setShift($request->user(), $data['status']);

            return response()->json([
                'message' => 'Shift set to '.RiderShift::label($data['status']).'.',
                'shift_status' => $rider->riderShiftStatus(),
                'can_accept_new_runs' => $rider->canAcceptNewRuns(),
                'user' => DeliveryApiPresenter::user($rider),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not set shift.',
            ], 422);
        }
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
            'alerts' => collect($alerts->items())->map(fn (StaffAlert $a) => DeliveryApiPresenter::alert($a))->values()->all(),
            'meta' => DeliveryApiPresenter::paginationMeta($alerts),
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

    public function runs(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        $orders = Order::query()
            ->kitchenDispatched()
            ->tap(fn ($q) => DeliveryAreaScope::applyKitchenDispatchedVisibleToRider($q, $rider))
            ->with([
                'menuItem',
                'user',
                'area',
                'deliveryRider',
                'orderGroup.kitchen',
                'orderGroup.area',
                'middoBoxes',
            ])
            ->orderByRaw("CASE order_status
                WHEN 'processing' THEN 1
                WHEN 'ready' THEN 2
                WHEN 'rider_assigned' THEN 3
                WHEN 'packed' THEN 4
                WHEN 'on_the_way_to_delivery' THEN 5
                ELSE 6 END")
            ->orderBy('id')
            ->paginate(50);

        return response()->json([
            'runs' => collect($orders->items())
                ->map(fn (Order $order) => DeliveryApiPresenter::run($order, $rider))
                ->values()
                ->all(),
            'meta' => DeliveryApiPresenter::paginationMeta($orders),
        ]);
    }

    public function showRun(Request $request, int $id): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        $order = Order::query()
            ->kitchenDispatched()
            ->tap(fn ($q) => DeliveryAreaScope::applyKitchenDispatchedVisibleToRider($q, $rider))
            ->with([
                'menuItem',
                'user',
                'area',
                'deliveryRider',
                'orderGroup.kitchen',
                'orderGroup.area',
                'middoBoxes',
            ])
            ->whereKey($id)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Run not found.'], 404);
        }

        return response()->json([
            'run' => DeliveryApiPresenter::run($order, $rider),
        ]);
    }

    public function pickupRun(Request $request, int $id): JsonResponse
    {
        try {
            $order = DeliveryMobileActions::pickUpOrder($id, (int) $request->user()->id);

            return response()->json([
                'message' => 'Picked up order #'.$order->id.'. Status is now On the way to delivery.',
                'run' => DeliveryApiPresenter::run($order, $request->user()),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not confirm pickup.',
            ], 422);
        }
    }

    public function deliverRun(Request $request, int $id): JsonResponse
    {
        try {
            $order = DeliveryMobileActions::deliverToConsumer($id, (int) $request->user()->id);

            return response()->json([
                'message' => 'Delivered order #'.$order->id.'. Boxes are now with the customer.',
                'run' => DeliveryApiPresenter::run($order, $request->user()),
                'order' => DeliveryApiPresenter::deliveredOrder($order),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not complete delivery.',
            ], 422);
        }
    }

    public function runsHistory(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();
        $riderId = (int) $rider->id;

        $period = (string) $request->query('period', 'this_month');
        if (! in_array($period, ['this_month', 'last_month', 'last_3_months'], true)) {
            $period = 'this_month';
        }

        $now = Carbon::now('Asia/Dhaka');

        [$start, $end, $label] = match ($period) {
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                $now->copy()->subMonthNoOverflow()->format('F Y'),
            ],
            'last_3_months' => [
                $now->copy()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
                $now->copy()->subMonthsNoOverflow(2)->format('M Y').' – '.$now->format('M Y'),
            ],
            default => [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
                $now->format('F Y'),
            ],
        };

        $orders = Order::query()
            ->where('delivery_rider_id', $riderId)
            ->whereIn('order_status', ['delivered', 'delivered_and_paid'])
            ->whereBetween('delivery_date', [$start, $end])
            ->with(['menuItem', 'area', 'orderGroup.kitchen'])
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'period' => $period,
            'label' => $label,
            'from' => $start,
            'to' => $end,
            'runs' => collect($orders->items())
                ->map(fn (Order $order) => DeliveryApiPresenter::historyRun($order))
                ->values()
                ->all(),
            'meta' => DeliveryApiPresenter::paginationMeta($orders),
        ]);
    }

    public function pendingBoxes(Request $request): JsonResponse
    {
        $payload = DeliveryMobileActions::pendingBoxesPayload($request->user());

        return response()->json($payload);
    }

    public function acceptWarehouse(Request $request, int $id): JsonResponse
    {
        try {
            $box = DeliveryMobileActions::acceptWarehouse($id, (int) $request->user()->id);

            return response()->json([
                'message' => "{$box->qr_code_id} accepted — deliver to kitchen, then mark handed.",
                'box' => DeliveryApiPresenter::box($box),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not accept this box.',
            ], 422);
        }
    }

    public function handToKitchen(Request $request, int $id): JsonResponse
    {
        try {
            $box = DeliveryMobileActions::handToKitchen($id, (int) $request->user()->id);

            return response()->json([
                'message' => "{$box->qr_code_id} handed to kitchen. Waiting for kitchen confirmation.",
                'box' => DeliveryApiPresenter::box($box),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not hand box to kitchen.',
            ], 422);
        }
    }

    public function acceptKitchenReturn(Request $request, int $id): JsonResponse
    {
        try {
            $box = DeliveryMobileActions::acceptKitchenReturn($id, (int) $request->user()->id);

            return response()->json([
                'message' => "{$box->qr_code_id} accepted — run started. Hand to Middo ops when you arrive.",
                'box' => DeliveryApiPresenter::box($box),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not accept this box.',
            ], 422);
        }
    }

    public function handToOps(Request $request, int $id): JsonResponse
    {
        try {
            $box = DeliveryMobileActions::handToOps($id, (int) $request->user()->id);

            return response()->json([
                'message' => "{$box->qr_code_id} handed to Middo ops — waiting for ops to mark received.",
                'box' => DeliveryApiPresenter::box($box),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not hand box to ops.',
            ], 422);
        }
    }

    public function collectEmpty(Request $request, int $id): JsonResponse
    {
        try {
            $box = DeliveryMobileActions::collectEmpty($id, (int) $request->user()->id);

            return response()->json([
                'message' => "{$box->qr_code_id} collected. Hand to kitchen when you arrive.",
                'box' => DeliveryApiPresenter::box($box),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not collect this box.',
            ], 422);
        }
    }

    public function acceptAllBoxes(Request $request, int $id): JsonResponse
    {
        $result = DeliveryMobileActions::acceptAllForRequest($id, (int) $request->user()->id);

        if ($result['accepted'] < 1 && $result['errors'] !== []) {
            return response()->json([
                'message' => $result['errors'][0],
            ], 422);
        }

        if ($result['accepted'] < 1) {
            return response()->json([
                'message' => 'No warehouse boxes ready to accept for this request.',
            ], 422);
        }

        return response()->json([
            'message' => "Accepted {$result['accepted']} ".str('box')->plural($result['accepted']).' — deliver to kitchen, then mark handed.',
            'accepted' => $result['accepted'],
            'errors' => $result['errors'],
        ]);
    }

    public function handAllBoxes(Request $request, int $id): JsonResponse
    {
        $result = DeliveryMobileActions::handAllForRequest($id, (int) $request->user()->id);

        if ($result['handed'] < 1 && $result['errors'] !== []) {
            return response()->json([
                'message' => $result['errors'][0],
            ], 422);
        }

        if ($result['handed'] < 1) {
            return response()->json([
                'message' => 'No accepted boxes ready to hand for this request.',
            ], 422);
        }

        return response()->json([
            'message' => "Handed {$result['handed']} ".str('box')->plural($result['handed']).' to kitchen. Waiting for kitchen confirmation.',
            'handed' => $result['handed'],
            'errors' => $result['errors'],
        ]);
    }

    public function deliveredOrders(Request $request): JsonResponse
    {
        $riderId = (int) $request->user()->id;

        $orders = Order::query()
            ->deliveredForRider($riderId)
            ->with(['menuItem', 'user', 'area'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json([
            'orders' => collect($orders->items())
                ->map(fn (Order $order) => DeliveryApiPresenter::deliveredOrder($order))
                ->values()
                ->all(),
            'meta' => DeliveryApiPresenter::paginationMeta($orders),
        ]);
    }

    public function collectCash(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'cash_amount' => ['nullable', 'integer', 'min:1'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'short_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cashAmount = (int) ($data['cash_amount'] ?? $data['amount'] ?? 0);
        if ($cashAmount < 1) {
            throw ValidationException::withMessages([
                'cash_amount' => ['Enter a cash amount of at least ৳1.'],
            ]);
        }

        $shortReason = $data['short_reason'] ?? $data['notes'] ?? null;

        try {
            $result = DeliveryMobileActions::collectCash(
                $id,
                (int) $request->user()->id,
                $cashAmount,
                $shortReason,
            );

            $rider = $request->user()->fresh();

            $message = $result['fully_paid']
                ? "Cash recorded for #{$id}. Due to Middo ৳{$result['due_to_middo']}."
                : "Short cash ৳{$cashAmount} recorded for #{$id}. Residual customer due ৳{$result['residual']}. Due to Middo so far ৳{$result['due_to_middo']}.";

            return response()->json([
                'message' => $message,
                'order' => DeliveryApiPresenter::deliveredOrder($result['order']),
                'due_to_middo' => $result['due_to_middo'],
                'commission' => $result['commission'],
                'cash_on_hand' => (int) $rider->balance,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not record cash payment.',
            ], 422);
        }
    }

    public function cashHandovers(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();
        $riderId = (int) $rider->id;

        $eligibleOrders = Order::query()
            ->with('menuItem')
            ->where('delivery_rider_id', $riderId)
            ->where('order_status', 'delivered_and_paid')
            ->where('payment_status', 'paid')
            ->whereDoesntHave('cashHandoverOrder')
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (Order $order) => $order->dueToMiddoAmount() > 0)
            ->values();

        $handovers = CashHandover::query()
            ->with(['items.order.menuItem'])
            ->where('rider_id', $riderId)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'cash_on_hand' => (int) $rider->balance,
            'due_to_middo' => (int) $rider->balance,
            'eligible_orders' => $eligibleOrders->map(fn (Order $order) => [
                'id' => $order->id,
                'menu_name' => $order->menuItem?->name,
                'due_to_middo' => $order->dueToMiddoAmount(),
            ])->all(),
            'handovers' => collect($handovers->items())
                ->map(fn (CashHandover $h) => DeliveryApiPresenter::handover($h))
                ->values()
                ->all(),
            'meta' => DeliveryApiPresenter::paginationMeta($handovers),
        ]);
    }

    public function createCashHandover(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'distinct'],
            'target' => ['required', 'string', 'in:kitchen,middo'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $handover = DeliveryMobileActions::createCashHandover(
                (int) $request->user()->id,
                array_map('intval', $data['order_ids']),
                $data['target'],
                $data['notes'] ?? null,
            );

            $label = $data['target'] === CashHandover::TARGET_MIDDO ? 'Middo/ops' : 'kitchen';

            return response()->json([
                'message' => "Due handover #{$handover->id} submitted for {$label} acceptance.",
                'handover' => DeliveryApiPresenter::handover($handover),
                'cash_on_hand' => (int) $request->user()->fresh()->balance,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not create cash handover.',
            ], 422);
        }
    }

    public function account(Request $request): JsonResponse
    {
        return response()->json(DeliveryApiPresenter::account($request->user()));
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'payout_channel' => ['nullable', 'in:'.implode(',', PayoutChannel::partnerChannels())],
        ]);

        try {
            $withdrawal = DeliveryMobileActions::requestWithdrawal(
                $request->user(),
                $data['notes'] ?? null,
                $data['payout_channel'] ?? null,
            );

            return response()->json([
                'message' => 'Withdrawal request submitted. Wallet reduced; waiting for Middo approval.',
                'withdrawal' => [
                    'id' => $withdrawal->id,
                    'amount' => (int) $withdrawal->amount,
                    'status' => $withdrawal->status,
                    'payout_channel' => $withdrawal->payout_channel,
                ],
                'balance' => DeliveryApiPresenter::account($request->user()->fresh())['balance'],
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not submit withdrawal.',
            ], 422);
        }
    }

    public function customRuns(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        $runs = CustomRun::query()
            ->with(['area', 'rider'])
            ->visibleToRider($rider)
            ->whereIn('status', [CustomRun::STATUS_PENDING, CustomRun::STATUS_STARTED])
            ->orderByRaw("CASE WHEN status = 'started' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'custom_runs' => collect($runs->items())
                ->map(fn (CustomRun $run) => DeliveryApiPresenter::customRun($run))
                ->values()
                ->all(),
            'meta' => DeliveryApiPresenter::paginationMeta($runs),
        ]);
    }

    public function startCustomRun(Request $request, int $id): JsonResponse
    {
        try {
            $run = DeliveryMobileActions::startCustomRun($id, (int) $request->user()->id);

            return response()->json([
                'message' => "Started custom run #{$run->id}.",
                'custom_run' => DeliveryApiPresenter::customRun($run),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not start custom run.',
            ], 422);
        }
    }

    public function completeCustomRun(Request $request, int $id): JsonResponse
    {
        try {
            $run = DeliveryMobileActions::completeCustomRun($id, (int) $request->user()->id);

            return response()->json([
                'message' => "Completed custom run #{$run->id}.",
                'custom_run' => DeliveryApiPresenter::customRun($run),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not complete custom run.',
            ], 422);
        }
    }
}
