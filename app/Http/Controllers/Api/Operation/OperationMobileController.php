<?php

namespace App\Http\Controllers\Api\Operation;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\OrderComplaint;
use App\Models\StaffAlert;
use App\Models\User;
use App\Models\UserLog;
use App\Support\OperationApiPresenter;
use App\Support\OpsDashboardMetrics;
use App\Support\OpsRiderBoard;
use App\Support\OpsSlaBoard;
use App\Support\StaffAlerts;
use App\Support\UserAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OperationMobileController extends Controller
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
            ->with('role')
            ->where('mobile', $credentials['mobile'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGIN_FAILED,
                source: UserAudit::SOURCE_OPERATION_MOBILE,
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
                source: UserAudit::SOURCE_OPERATION_MOBILE,
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

        if ($user->role?->name !== 'operation') {
            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGIN_BLOCKED,
                source: UserAudit::SOURCE_OPERATION_MOBILE,
                performedBy: $user->id,
                metadata: [
                    'reason' => 'wrong_role',
                    'role' => $user->role?->name,
                    'device_name' => $credentials['device_name'] ?? null,
                ],
            );

            return response()->json([
                'message' => 'Login as Operation to continue.',
            ], 403);
        }

        $token = $user->createToken(
            $credentials['device_name'] ?? 'middo-operation-mobile'
        )->plainTextToken;

        UserAudit::record(
            user: $user,
            event: UserLog::EVENT_LOGIN,
            source: UserAudit::SOURCE_OPERATION_MOBILE,
            performedBy: $user->id,
            metadata: [
                'device_name' => $credentials['device_name'] ?? 'middo-operation-mobile',
            ],
        );

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => OperationApiPresenter::user($user),
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
                source: UserAudit::SOURCE_OPERATION_MOBILE,
                performedBy: $user->id,
            );
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => OperationApiPresenter::user($request->user()),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        UserAudit::record(
            user: $user,
            event: UserLog::EVENT_PASSWORD_CHANGED,
            source: UserAudit::SOURCE_OPERATION_MOBILE,
            performedBy: $user->id,
        );

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
        $metrics = OpsDashboardMetrics::forRole('operation');
        $sla = OpsSlaBoard::counts();
        $riders = OpsRiderBoard::counts();
        $userId = (int) $request->user()->id;

        $slaCount = (int) ($sla['unassigned_closed'] ?? 0) + (int) ($sla['late_to_pack'] ?? 0);
        $money = $metrics['money'] ?? [];
        $boxRequests = (int) ($metrics['box_requests']['open'] ?? 0);

        $openComplaints = OrderComplaint::query()
            ->where('status', OrderComplaint::STATUS_OPEN)
            ->where('is_reply', false)
            ->count();

        return response()->json([
            'tiles' => [
                OperationApiPresenter::dashboardTile('alerts', 'Alerts', StaffAlerts::unreadCount($userId)),
                OperationApiPresenter::dashboardTile('sla', 'Dispatch SLA', $slaCount),
                OperationApiPresenter::dashboardTile(
                    'awaiting_rider',
                    'Packed · awaiting rider',
                    (int) ($riders['awaiting'] ?? 0)
                ),
                OperationApiPresenter::dashboardTile('box_requests', 'Box requests', $boxRequests),
                OperationApiPresenter::dashboardTile(
                    'cash_handovers',
                    'Middo cash handovers',
                    (int) ($money['pending_middo_handovers'] ?? 0)
                ),
                OperationApiPresenter::dashboardTile('complaints', 'Open complaints', $openComplaints),
            ],
            'today' => [
                'date' => $metrics['today_date'] ?? null,
                'label' => $metrics['today_label'] ?? null,
                'orders' => (int) (($metrics['today']['orders'] ?? 0)),
                'qty' => (int) (($metrics['today']['qty'] ?? 0)),
            ],
            'tomorrow' => [
                'date' => $metrics['tomorrow_date'] ?? null,
                'label' => $metrics['tomorrow_label'] ?? null,
                'orders' => (int) (($metrics['tomorrow']['orders'] ?? 0)),
                'qty' => (int) (($metrics['tomorrow']['qty'] ?? 0)),
            ],
            'attention' => $metrics['attention'] ?? [],
            'money' => [
                'pending_middo_handovers' => (int) ($money['pending_middo_handovers'] ?? 0),
                'pending_middo_handover_amount' => (int) ($money['pending_middo_handover_amount'] ?? 0),
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
            'alerts' => collect($alerts->items())
                ->map(fn (StaffAlert $alert) => OperationApiPresenter::alert($alert))
                ->values()
                ->all(),
            'meta' => OperationApiPresenter::paginationMeta($alerts),
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
}
