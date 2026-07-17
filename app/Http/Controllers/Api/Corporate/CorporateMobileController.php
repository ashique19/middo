<?php

namespace App\Http\Controllers\Api\Corporate;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\Role;
use App\Models\User;
use App\Support\CorporateApiPresenter;
use App\Support\CorporateOrderLimit;
use App\Support\OrderConfirmationOtp;
use App\Support\PasswordResetOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CorporateMobileController extends Controller
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
            throw ValidationException::withMessages([
                'mobile' => ['Invalid mobile number or password.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Account is not active.',
                'status' => $user->status,
            ], 403);
        }

        if ($user->role?->name !== 'corporate') {
            return response()->json([
                'message' => 'Login as Corporate to continue.',
            ], 403);
        }

        $token = $user->createToken(
            $credentials['device_name'] ?? 'middo-corporate-mobile'
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => CorporateApiPresenter::user($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function locations(): JsonResponse
    {
        return response()->json([
            'cities' => CorporateApiPresenter::citiesWithAreas(),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:255'],
            'last_name' => ['required', 'string', 'min:2', 'max:255'],
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => ['required', 'string', 'min:4', 'max:255'],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
            'mobile.unique' => 'This mobile number is already registered.',
        ]);

        $this->assertAreaBelongsToCity((int) $data['city_id'], (int) $data['area_id']);

        $role = Role::query()->where('name', 'corporate')->firstOrFail();

        // users.full_name is a generated column and there is no company_name
        // field, so store the company as first_name and keep the contact person
        // on the address line for ops reference.
        $contact = trim($data['first_name'].' '.$data['last_name']);
        $user = User::create([
            'first_name' => $data['company_name'],
            'last_name' => '',
            'mobile' => $data['mobile'],
            'password' => $data['password'],
            'address' => $data['address'].($contact !== '' ? ' (Contact: '.$contact.')' : ''),
            'city_id' => $data['city_id'],
            'area_id' => $data['area_id'],
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => false,
            'balance' => 0,
        ]);

        $user->load(['role', 'area', 'city']);

        $token = $user->createToken(
            $data['device_name'] ?? 'middo-corporate-mobile'
        )->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => CorporateApiPresenter::user($user),
        ], 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
        ]);

        $user = User::query()
            ->with('role')
            ->where('mobile', $data['mobile'])
            ->first();

        // Always return a generic success to avoid account enumeration.
        if (! $user || $user->role?->name !== 'corporate') {
            return response()->json([
                'message' => 'If this mobile is registered, a reset code has been sent.',
                'mobile' => $data['mobile'],
                'expires_in' => 300,
            ]);
        }

        $result = PasswordResetOtp::send($data['mobile']);

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'mobile' => [$result['message']],
            ]);
        }

        return response()->json([
            'message' => 'If this mobile is registered, a reset code has been sent.',
            'mobile' => $data['mobile'],
            'expires_in' => 300,
            'debug_otp' => $result['debug_otp'] ?? null,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'otp' => ['required', 'string', 'size:4'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
            'otp.size' => 'Enter the 4-digit reset code sent by SMS.',
        ]);

        $user = User::query()
            ->with('role')
            ->where('mobile', $data['mobile'])
            ->first();

        if (! $user || $user->role?->name !== 'corporate') {
            throw ValidationException::withMessages([
                'mobile' => ['Unable to reset password for this mobile number.'],
            ]);
        }

        if (! PasswordResetOtp::verify($data['mobile'], $data['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired reset code.'],
            ]);
        }

        $user->update([
            'password' => $data['password'],
            'is_mobile_verified' => true,
        ]);

        return response()->json([
            'message' => 'Password updated. You can sign in with your new password.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => CorporateApiPresenter::user($request->user()),
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
            'user' => CorporateApiPresenter::user($user),
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

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $today = now('Asia/Dhaka')->toDateString();

        $activeOrdersCount = Order::query()
            ->where('user_id', $userId)
            ->whereIn('order_status', ['pending', 'processing', 'on_the_way_to_delivery'])
            ->count();

        $nextMeal = Order::query()
            ->where('user_id', $userId)
            ->where('delivery_date', '>=', $today)
            ->whereIn('order_status', ['pending', 'processing', 'on_the_way_to_delivery'])
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->first();

        $boxesInCustody = MiddoBox::query()
            ->where('held_by_user_id', $userId)
            ->where('asset_status', 'active')
            ->count();

        $monthlySpend = (float) Order::query()
            ->where('user_id', $userId)
            ->whereYear('delivery_date', Carbon::now('Asia/Dhaka')->year)
            ->whereMonth('delivery_date', Carbon::now('Asia/Dhaka')->month)
            ->where('order_status', '!=', 'cancelled')
            ->sum('total_amount');

        $upcoming = Order::with('menuItem')
            ->where('user_id', $userId)
            ->where('delivery_date', '>=', $today)
            ->where('order_status', '!=', 'cancelled')
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->take(3)
            ->get()
            ->map(fn (Order $order) => CorporateApiPresenter::order($order))
            ->values();

        $recent = Order::with('menuItem')
            ->where('user_id', $userId)
            ->where('delivery_date', '<', $today)
            ->orderByDesc('delivery_date')
            ->take(5)
            ->get()
            ->map(fn (Order $order) => CorporateApiPresenter::order($order))
            ->values();

        return response()->json([
            'user' => CorporateApiPresenter::user($user),
            'metrics' => [
                'active_orders' => $activeOrdersCount,
                'next_meal' => $nextMeal
                    ? ($nextMeal->delivery_time ?: '12:00 PM')
                    : 'None',
                'next_delivery_hint' => $nextMeal
                    ? Carbon::parse($nextMeal->delivery_date)->format('M d')
                    : 'No upcoming meal',
                'monthly_spend' => $monthlySpend,
                'monthly_saved' => round($monthlySpend * 0.1, 0),
                'boxes_in_custody' => $boxesInCustody,
            ],
            'upcoming_orders' => $upcoming,
            'recent_orders' => $recent,
        ]);
    }

    public function menu(): JsonResponse
    {
        $items = MenuItem::query()
            ->where('is_featured', true)
            ->orderBy('display_order')
            ->take(24)
            ->get()
            ->map(fn (MenuItem $item) => CorporateApiPresenter::menuItem($item))
            ->values();

        return response()->json([
            'items' => $items,
            'checkout_meta' => CorporateApiPresenter::availableDates(),
        ]);
    }

    public function scheduled(Request $request): JsonResponse
    {
        $orders = Order::with('menuItem')
            ->where('user_id', $request->user()->id)
            ->where('delivery_date', '>=', now('Asia/Dhaka')->toDateString())
            ->where('order_status', '!=', 'cancelled')
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->get()
            ->map(fn (Order $order) => CorporateApiPresenter::order($order))
            ->values();

        return response()->json(['orders' => $orders]);
    }

    public function history(Request $request): JsonResponse
    {
        $orders = Order::with('menuItem')
            ->where('user_id', $request->user()->id)
            ->where('delivery_date', '<', now('Asia/Dhaka')->toDateString())
            ->orderByDesc('delivery_date')
            ->take(50)
            ->get()
            ->map(fn (Order $order) => CorporateApiPresenter::order($order))
            ->values();

        return response()->json(['orders' => $orders]);
    }

    public function sendOrderOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receiver_name' => ['required', 'string', 'min:2', 'max:120'],
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'address' => ['required', 'string', 'min:5', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'delivery_time' => ['nullable', 'string', 'max:40'],
            'dates' => ['required', 'array', 'min:1'],
            'dates.*.date' => ['required', 'date_format:Y-m-d'],
            'dates.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
        ]);

        $this->assertAreaBelongsToCity((int) $data['city_id'], (int) $data['area_id']);
        $this->assertDailyLimits($request->user()->id, $data['dates']);

        $result = OrderConfirmationOtp::send($data['mobile']);

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'mobile' => [$result['message']],
            ]);
        }

        return response()->json([
            'message' => $result['message'],
            'mobile' => $data['mobile'],
            'expires_in' => 300,
            'debug_otp' => $result['debug_otp'] ?? null,
        ]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'delivery_time' => ['nullable', 'string', 'max:40'],
            'dates' => ['required', 'array', 'min:1'],
            'dates.*.date' => ['required', 'date_format:Y-m-d'],
            'dates.*.quantity' => ['required', 'integer', 'min:1'],
            'receiver_name' => ['required', 'string', 'min:2', 'max:120'],
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'address' => ['required', 'string', 'min:5', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'otp' => ['required', 'string', 'size:4'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
            'otp.size' => 'Enter the 4-digit confirmation code sent by SMS.',
        ]);

        if (! OrderConfirmationOtp::verify($data['mobile'], $data['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired confirmation code.'],
            ]);
        }

        $this->assertAreaBelongsToCity((int) $data['city_id'], (int) $data['area_id']);
        $this->assertDailyLimits($request->user()->id, $data['dates']);

        /** @var User $user */
        $user = $request->user();
        $menuItem = MenuItem::query()->findOrFail($data['menu_item_id']);
        $deliveryTime = $data['delivery_time'] ?? '12:00 PM';
        $city = City::query()->findOrFail($data['city_id']);
        $area = Area::query()->findOrFail($data['area_id']);
        $fullAddress = trim($data['address']).', '.$area->name.', '.$city->name;

        $created = [];

        DB::transaction(function () use ($data, $user, $menuItem, $deliveryTime, $fullAddress, &$created) {
            foreach ($data['dates'] as $line) {
                $date = $line['date'];
                $qty = (int) $line['quantity'];

                $order = Order::create([
                    'user_id' => $user->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $qty,
                    'delivery_date' => $date,
                    'delivery_time' => $deliveryTime,
                    'total_amount' => (int) round($menuItem->price * $qty),
                    'address' => $fullAddress,
                    'order_status' => 'pending',
                    'payment_status' => 'pending',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $created[] = CorporateApiPresenter::order($order->load('menuItem'));
            }

            $nameParts = preg_split('/\s+/', trim($data['receiver_name']), 2) ?: [trim($data['receiver_name'])];
            $user->update([
                'first_name' => $nameParts[0] ?: $user->first_name,
                'last_name' => $nameParts[1] ?? ($user->last_name ?: ''),
                'mobile' => $data['mobile'],
                'address' => $data['address'],
                'city_id' => $data['city_id'],
                'area_id' => $data['area_id'],
                'is_mobile_verified' => true,
            ]);
        });

        return response()->json([
            'message' => 'Your meal track has been scheduled successfully.',
            'orders' => $created,
        ], 201);
    }

    /**
     * @param  list<array{date: string, quantity: int}>  $dates
     */
    private function assertDailyLimits(int $userId, array $dates): void
    {
        foreach ($dates as $line) {
            $date = $line['date'];
            $qty = (int) $line['quantity'];

            if (CorporateOrderLimit::exceedsDailyLimit($userId, $date, $qty)) {
                throw ValidationException::withMessages([
                    'dates' => [
                        sprintf(
                            'Daily limit exceeded for %s. Max %d meals/day.',
                            $date,
                            CorporateOrderLimit::maxAllowed()
                        ),
                    ],
                ]);
            }
        }
    }

    private function assertAreaBelongsToCity(int $cityId, int $areaId): void
    {
        $belongs = Area::query()
            ->whereKey($areaId)
            ->where('city_id', $cityId)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'area_id' => ['Selected area does not belong to the chosen city.'],
            ]);
        }
    }

    public function updateOrder(Request $request, int $order): JsonResponse
    {
        $model = $this->findOwnedPendingOrder($request->user()->id, $order);
        $date = optional($model->delivery_date)->toDateString()
            ?? now('Asia/Dhaka')->toDateString();
        $maxQty = max(1, CorporateOrderLimit::remainingQtyForDate(
            $request->user()->id,
            $date,
            $model->id
        ));

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$maxQty],
        ], [
            'quantity.max' => sprintf(
                'Maximum %d meals allowed per day. You can set up to %d for this order.',
                CorporateOrderLimit::maxAllowed(),
                $maxQty
            ),
        ]);

        $model->loadMissing('menuItem');
        $model->update([
            'quantity' => (int) $data['quantity'],
            'total_amount' => (int) round(($model->menuItem?->price ?? 0) * (int) $data['quantity']),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Order updated.',
            'order' => CorporateApiPresenter::order($model->fresh('menuItem')),
            'max_quantity' => $maxQty,
        ]);
    }

    public function cancelOrder(Request $request, int $order): JsonResponse
    {
        $model = $this->findOwnedPendingOrder($request->user()->id, $order);
        $refund = (float) $model->total_amount;

        DB::transaction(function () use ($request, $model, $refund) {
            $request->user()->increment('balance', $refund);
            $model->delete();
        });

        return response()->json([
            'message' => 'Order cancelled and amount credited to Middo Balance.',
            'refunded_amount' => $refund,
            'balance' => (float) $request->user()->fresh()->balance,
        ]);
    }

    public function track(Request $request, int $order): JsonResponse
    {
        $model = Order::with('menuItem')
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $events = $model->logs()
            ->with('performedBy:id,first_name,last_name')
            ->latest()
            ->get()
            ->values();

        $mapped = $events->map(fn ($log) => CorporateApiPresenter::trackEvent($log))->all();
        if ($mapped !== []) {
            $mapped[0]['is_current'] = true;
        }

        return response()->json([
            'order' => CorporateApiPresenter::order($model),
            'events' => $mapped,
        ]);
    }

    private function findOwnedPendingOrder(int $userId, int $orderId): Order
    {
        $order = Order::with('menuItem')
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->where('order_status', 'pending')
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'order' => ['Only pending orders can be edited or cancelled.'],
            ]);
        }

        return $order;
    }

    public function supportThread(Request $request, int $order): JsonResponse
    {
        $model = Order::with('menuItem')
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $root = OrderComplaint::threadForOrder($model->id);
        $messages = $root
            ? $root->threadMessages()->map(
                fn (OrderComplaint $entry) => CorporateApiPresenter::supportMessage($entry)
            )->values()
            : collect();

        return response()->json([
            'order' => CorporateApiPresenter::order($model),
            'has_existing_complaint' => (bool) $root,
            'messages' => $messages,
        ]);
    }

    public function supportMessage(Request $request, int $order): JsonResponse
    {
        $model = Order::query()
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (OrderComplaint::threadForOrder($model->id)) {
            return response()->json([
                'message' => 'A support request already exists for this order.',
            ], 422);
        }

        $data = $request->validate([
            'category' => ['required', 'in:delivery,food_quality,payment,other'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $complaint = OrderComplaint::create([
            'order_id' => $model->id,
            'parent_id' => null,
            'is_reply' => false,
            'category' => $data['category'],
            'message' => $data['message'],
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $complaint->load('createdBy:id,first_name,last_name');

        return response()->json([
            'message' => 'Your complaint/support request has been submitted.',
            'entry' => CorporateApiPresenter::supportMessage($complaint),
        ], 201);
    }

    public function topUp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:100', 'max:500000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $amount = (int) round($data['amount']);

        $user->balance = (int) $user->balance + $amount;
        $user->save();

        return response()->json([
            'message' => 'Balance topped up successfully.',
            'user' => CorporateApiPresenter::user($user->fresh(['area', 'city', 'role'])),
        ]);
    }
}
