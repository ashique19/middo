<?php

namespace App\Http\Controllers\Api\Corporate;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\City;
use App\Models\DeviceToken;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Models\UserLog;
use App\Models\WalletTransaction;
use App\Support\CorporateApiPresenter;
use App\Support\CorporateGatewayPrepay;
use App\Support\CorporateOrderLimit;
use App\Support\CorporateOrderPrepayment;
use App\Support\CorporateWalletTopUp;
use App\Support\MealOrderGrouper;
use App\Support\OrderConfirmationOtp;
use App\Support\OrderCutoff;
use App\Support\OrderPaymentMethod;
use App\Support\PackageBilling;
use App\Support\PackageSubscriptionService;
use App\Support\PasswordResetOtp;
use App\Support\SignupOtp;
use App\Support\UserAudit;
use App\Support\WalletLedger;
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
            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGIN_FAILED,
                source: UserAudit::SOURCE_CORPORATE_MOBILE,
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
                source: UserAudit::SOURCE_CORPORATE_MOBILE,
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

        if ($user->role?->name !== 'corporate') {
            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGIN_BLOCKED,
                source: UserAudit::SOURCE_CORPORATE_MOBILE,
                performedBy: $user->id,
                metadata: [
                    'reason' => 'wrong_role',
                    'role' => $user->role?->name,
                    'device_name' => $credentials['device_name'] ?? null,
                ],
            );

            return response()->json([
                'message' => 'Login as Corporate to continue.',
            ], 403);
        }

        $token = $user->createToken(
            $credentials['device_name'] ?? 'middo-corporate-mobile'
        )->plainTextToken;

        UserAudit::record(
            user: $user,
            event: UserLog::EVENT_LOGIN,
            source: UserAudit::SOURCE_CORPORATE_MOBILE,
            performedBy: $user->id,
            metadata: [
                'device_name' => $credentials['device_name'] ?? 'middo-corporate-mobile',
            ],
        );

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => CorporateApiPresenter::user($user),
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
                source: UserAudit::SOURCE_CORPORATE_MOBILE,
                performedBy: $user->id,
            );
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function locations(): JsonResponse
    {
        return response()->json([
            'cities' => CorporateApiPresenter::citiesWithAreas(),
        ]);
    }

    public function sendSignupOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/', 'unique:users,mobile'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
            'mobile.unique' => 'This mobile number is already registered.',
        ]);

        $result = SignupOtp::send($data['mobile']);

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

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:255'],
            'last_name' => ['required', 'string', 'min:2', 'max:255'],
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/', 'unique:users,mobile'],
            'otp' => ['required', 'string', 'size:4'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => ['required', 'string', 'min:4', 'max:255'],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
            'mobile.unique' => 'This mobile number is already registered.',
            'otp.size' => 'Enter the 4-digit verification code sent by SMS.',
        ]);

        $this->assertAreaBelongsToCity((int) $data['city_id'], (int) $data['area_id']);

        if (! SignupOtp::verify($data['mobile'], $data['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired verification code.'],
            ]);
        }

        $role = Role::query()->where('name', 'corporate')->firstOrFail();

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company_name' => $data['company_name'],
            'mobile' => $data['mobile'],
            'password' => $data['password'],
            'address' => $data['address'],
            'city_id' => $data['city_id'],
            'area_id' => $data['area_id'],
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
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
            'company_name' => ['nullable', 'string', 'max:255'],
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
        if (array_key_exists('company_name', $data)) {
            $user->company_name = $data['company_name'] ?: null;
        }
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
            'message' => 'Device registered for notifications.',
            'token_id' => $token->id,
        ]);
    }

    public function unregisterDeviceToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json([
            'message' => 'Device unregistered.',
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $today = now('Asia/Dhaka')->toDateString();

        $activeOrdersCount = Order::query()
            ->where('user_id', $userId)
            ->active()
            ->count();

        $nextMeal = Order::query()
            ->where('user_id', $userId)
            ->where('delivery_date', '>=', $today)
            ->active()
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
        $this->assertDeliveryDatesOpen($data['dates']);

        $menuItem = MenuItem::query()->findOrFail($data['menu_item_id']);
        $prepayment = $this->prepaymentQuote($request->user(), $data, $menuItem);
        $codAllowed = OrderPaymentMethod::allowsCashOnDelivery(
            (bool) $prepayment['required'],
            count($data['dates'])
        );

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
            'prepayment' => $prepayment,
            'cod_allowed' => $codAllowed,
            'payment_methods' => $prepayment['required']
                ? [OrderPaymentMethod::BALANCE, OrderPaymentMethod::GATEWAY]
                : ($codAllowed
                    ? OrderPaymentMethod::all()
                    : [OrderPaymentMethod::CASH_ON_DELIVERY]),
        ]);
    }

    public function createGatewayPrepay(Request $request): JsonResponse
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
        ]);

        $this->assertAreaBelongsToCity((int) $data['city_id'], (int) $data['area_id']);
        $this->assertDailyLimits($request->user()->id, $data['dates']);
        $this->assertDeliveryDatesOpen($data['dates']);

        $menuItem = MenuItem::query()->findOrFail($data['menu_item_id']);
        $prepayment = $this->prepaymentQuote($request->user(), $data, $menuItem);
        $activeDateCount = count($data['dates']);
        $codAllowed = OrderPaymentMethod::allowsCashOnDelivery(
            (bool) $prepayment['required'],
            $activeDateCount
        );

        $cartTotal = 0;
        foreach ($data['dates'] as $line) {
            $cartTotal += (int) round($menuItem->price * (int) $line['quantity']);
        }

        $chargeAmount = $prepayment['required']
            ? (int) $prepayment['amount']
            : ($codAllowed ? $cartTotal : 0);

        if ($chargeAmount <= 0) {
            throw ValidationException::withMessages([
                'payment_method' => ['Online payment is not available for this cart. Choose Cash on Delivery.'],
            ]);
        }

        $session = CorporateGatewayPrepay::create(
            $request->user()->id,
            $chargeAmount,
            $this->cartFingerprint($data, $chargeAmount)
        );

        return response()->json([
            'message' => 'Complete gateway payment, then place the order with the payment token.',
            'payment_token' => $session['token'],
            'payment_url' => $session['payment_url'] ?? app(PaymentGateway::class)->paymentUrl($session['token']),
            'amount' => $session['amount'],
            'driver' => app(PaymentGateway::class)->driver(),
            'prepayment' => $prepayment,
            'cod_allowed' => $codAllowed,
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
            'payment_method' => ['nullable', 'in:balance,gateway,cash_on_delivery'],
            'payment_token' => ['nullable', 'string', 'max:80'],
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
        $this->assertDeliveryDatesOpen($data['dates']);

        /** @var User $user */
        $user = $request->user();
        $menuItem = MenuItem::query()->findOrFail($data['menu_item_id']);
        $deliveryTime = $data['delivery_time'] ?? '12:00 PM';
        $city = City::query()->findOrFail($data['city_id']);
        $area = Area::query()->findOrFail($data['area_id']);
        $fullAddress = trim($data['address']).', '.$area->name.', '.$city->name;
        $prepayment = $this->prepaymentQuote($user, $data, $menuItem);
        $activeDateCount = count($data['dates']);

        $paymentMethod = $data['payment_method'] ?? null;
        if ($prepayment['required']) {
            if (! in_array($paymentMethod, [OrderPaymentMethod::BALANCE, OrderPaymentMethod::GATEWAY], true)) {
                throw ValidationException::withMessages([
                    'payment_method' => [
                        $prepayment['message'] ?? 'Prepayment is required. Pay from Middo Balance or payment gateway.',
                    ],
                    'prepayment' => [$prepayment['message'] ?? 'Prepayment required.'],
                ]);
            }
        } elseif (OrderPaymentMethod::allowsCashOnDelivery(false, $activeDateCount)) {
            if ($paymentMethod === null || $paymentMethod === '') {
                $paymentMethod = OrderPaymentMethod::CASH_ON_DELIVERY;
            }
            if (! in_array($paymentMethod, OrderPaymentMethod::all(), true)) {
                throw ValidationException::withMessages([
                    'payment_method' => ['Choose Cash on Delivery, Middo Balance, or online payment.'],
                ]);
            }
        } else {
            // Multi-date carts without forced prepayment settle as COD.
            $paymentMethod = OrderPaymentMethod::CASH_ON_DELIVERY;
        }

        $lineTotals = [];
        foreach ($data['dates'] as $line) {
            $lineTotals[] = (int) round($menuItem->price * (int) $line['quantity']);
        }
        $cartTotal = (int) array_sum($lineTotals);
        $chargeAmount = $paymentMethod === OrderPaymentMethod::CASH_ON_DELIVERY
            ? 0
            : ($prepayment['required'] ? (int) $prepayment['amount'] : $cartTotal);

        if ($chargeAmount > 0 && $paymentMethod === OrderPaymentMethod::BALANCE && (int) $user->balance < $chargeAmount) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    sprintf(
                        'Insufficient Middo Balance. Need ৳%s, available ৳%s. Top up or use payment gateway.',
                        number_format($chargeAmount),
                        number_format((int) $user->balance)
                    ),
                ],
            ]);
        }

        if ($chargeAmount > 0 && $paymentMethod === OrderPaymentMethod::GATEWAY) {
            $token = (string) ($data['payment_token'] ?? '');
            if ($token === '') {
                throw ValidationException::withMessages([
                    'payment_token' => ['Complete gateway payment first, then submit the payment token.'],
                ]);
            }

            $consumed = CorporateGatewayPrepay::consumePaidToken(
                $token,
                $user->id,
                $chargeAmount,
                $this->cartFingerprint($data, $chargeAmount)
            );

            if (! ($consumed['ok'] ?? false)) {
                throw ValidationException::withMessages([
                    'payment_token' => [$consumed['message'] ?? 'Invalid gateway payment.'],
                ]);
            }
        }

        $prepaidAllocations = CorporateOrderPrepayment::allocate($chargeAmount, $lineTotals);

        $created = [];
        $profileMatches = CorporateOrderPrepayment::profileMatchesReceiver(
            $user,
            $data['receiver_name'],
            $data['mobile']
        );

        DB::transaction(function () use (
            $data,
            $user,
            $menuItem,
            $deliveryTime,
            $fullAddress,
            $paymentMethod,
            $prepayment,
            $prepaidAllocations,
            $profileMatches,
            $chargeAmount,
            &$created
        ) {
            if ($chargeAmount > 0 && $paymentMethod === OrderPaymentMethod::BALANCE) {
                try {
                    WalletLedger::debit(
                        $user,
                        $chargeAmount,
                        $prepayment['required'] ? 'Order prepayment' : 'Order payment'
                    );
                    $user->refresh();
                } catch (\RuntimeException $e) {
                    throw ValidationException::withMessages([
                        'payment_method' => [$e->getMessage()],
                    ]);
                }
            }

            foreach ($data['dates'] as $index => $line) {
                $date = $line['date'];
                $qty = (int) $line['quantity'];
                $lineTotal = (int) round($menuItem->price * $qty);
                $amountPaid = (int) ($prepaidAllocations[$index] ?? 0);

                $order = Order::create([
                    'user_id' => $user->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $qty,
                    'delivery_date' => $date,
                    'delivery_time' => $deliveryTime,
                    'total_amount' => $lineTotal,
                    'amount_paid' => $amountPaid,
                    'prepaid_amount' => $amountPaid,
                    'cash_collected' => 0,
                    'address' => $fullAddress,
                    'receiver_name' => $data['receiver_name'],
                    'receiver_mobile' => $data['mobile'],
                    'area_id' => $data['area_id'],
                    'order_status' => 'pending',
                    'payment_status' => $amountPaid >= $lineTotal && $lineTotal > 0 ? 'paid' : 'pending',
                    'payment_method' => $paymentMethod,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                app(MealOrderGrouper::class)->assignOrder($order->fresh(['user']), $user->id);

                $created[] = CorporateApiPresenter::order($order->load('menuItem'));
            }

            $profileUpdate = [
                'address' => $data['address'],
                'city_id' => $data['city_id'],
                'area_id' => $data['area_id'],
                'is_mobile_verified' => true,
            ];

            // Only sync identity onto the account when receiver matches the profile.
            if ($profileMatches) {
                $nameParts = preg_split('/\s+/', trim($data['receiver_name']), 2) ?: [trim($data['receiver_name'])];
                $profileUpdate['first_name'] = $nameParts[0] ?: $user->first_name;
                $profileUpdate['last_name'] = $nameParts[1] ?? ($user->last_name ?: '');
                $profileUpdate['mobile'] = $data['mobile'];
            }

            $user->update($profileUpdate);
        });

        return response()->json([
            'message' => 'Your meal track has been scheduled successfully.',
            'orders' => $created,
            'prepayment' => $prepayment,
            'balance' => (int) $user->fresh()->balance,
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepaymentQuote(User $user, array $data, MenuItem $menuItem): array
    {
        $cartTotal = 0;
        foreach ($data['dates'] as $line) {
            $cartTotal += (int) round($menuItem->price * (int) $line['quantity']);
        }

        return CorporateOrderPrepayment::evaluate(
            $user,
            $data['receiver_name'],
            $data['mobile'],
            count($data['dates']),
            $cartTotal
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cartFingerprint(array $data, int $amount): array
    {
        $dates = collect($data['dates'])
            ->map(fn ($line) => [
                'date' => $line['date'],
                'quantity' => (int) $line['quantity'],
            ])
            ->sortBy('date')
            ->values()
            ->all();

        return [
            'menu_item_id' => (int) $data['menu_item_id'],
            'delivery_time' => $data['delivery_time'] ?? '12:00 PM',
            'receiver_name' => CorporateOrderPrepayment::normalizeName($data['receiver_name']),
            'mobile' => CorporateOrderPrepayment::normalizeMobile($data['mobile']),
            'address' => trim($data['address']),
            'city_id' => (int) $data['city_id'],
            'area_id' => (int) $data['area_id'],
            'dates' => $dates,
            'amount' => $amount,
        ];
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

    /**
     * @param  list<array{date: string, quantity: int}>  $dates
     */
    private function assertDeliveryDatesOpen(array $dates): void
    {
        foreach ($dates as $line) {
            $date = (string) $line['date'];

            if (OrderCutoff::isPastForDeliveryDate($date)) {
                throw ValidationException::withMessages([
                    'dates' => [OrderCutoff::placementDeniedMessage($date)],
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
        // Order editing has been removed; cancel and place a new order instead.
        throw ValidationException::withMessages([
            'order' => ['Orders can no longer be edited. Cancel this order and place a new one if you need changes.'],
        ]);
    }

    public function cancelOrder(Request $request, int $order): JsonResponse
    {
        $model = $this->findOwnedPendingOrder($request->user()->id, $order);
        $refund = (int) ($model->amount_paid ?? 0);

        DB::transaction(function () use ($request, $model, $refund) {
            if ($refund > 0) {
                WalletLedger::credit(
                    $request->user(),
                    $refund,
                    WalletTransaction::TYPE_REFUND,
                    'Refund for cancelled order #'.$model->id,
                    $model
                );
            }
            $model->update([
                'order_status' => 'cancelled',
                'updated_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => $refund > 0
                ? 'Order cancelled and prepaid amount credited to Middo Balance.'
                : 'Order cancelled.',
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

        if (! OrderCutoff::allowsModification($order)) {
            throw ValidationException::withMessages([
                'order' => [OrderCutoff::modificationDeniedMessage()],
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

        $checkout = CorporateWalletTopUp::createCheckout($user, $amount);

        return response()->json([
            'message' => 'Complete payment in the Middo checkout to credit your balance.',
            'token' => $checkout['token'],
            'amount' => $checkout['amount'],
            'payment_url' => $checkout['payment_url'],
            'user' => CorporateApiPresenter::user($user->fresh(['area', 'city', 'role'])),
        ]);
    }

    public function walletTransactions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rows = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => (int) $tx->amount,
                'balance_after' => (int) $tx->balance_after,
                'description' => $tx->description,
                'at' => optional($tx->created_at)?->timezone('Asia/Dhaka')->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'balance' => (int) $user->balance,
            'transactions' => $rows,
        ]);
    }

    public function markBoxReadyForPickup(Request $request, int $boxId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $box = MiddoBox::query()
            ->where('id', $boxId)
            ->where('held_by_user_id', $user->id)
            ->where('asset_status', 'active')
            ->first();

        if (! $box) {
            return response()->json(['message' => 'Box not found or not in your custody.'], 404);
        }

        $box->update([
            'ready_for_pickup' => true,
            'ready_for_pickup_at' => now(),
        ]);

        return response()->json([
            'message' => 'Box marked as ready for pickup. A rider will collect it on the next run.',
            'box' => [
                'id' => $box->id,
                'qr_code_id' => $box->qr_code_id,
                'ready_for_pickup' => true,
                'ready_for_pickup_at' => $box->ready_for_pickup_at?->toIso8601String(),
            ],
        ]);
    }

    public function boxes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $boxes = MiddoBox::query()
            ->where('held_by_user_id', $user->id)
            ->where('asset_status', 'active')
            ->orderBy('qr_code_id')
            ->get()
            ->map(fn (MiddoBox $box) => [
                'id' => $box->id,
                'qr_code_id' => $box->qr_code_id,
                'box_model_type' => $box->box_model_type,
                'location_label' => 'At your office',
                'ready_for_pickup' => (bool) $box->ready_for_pickup,
                'ready_for_pickup_at' => $box->ready_for_pickup_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'count' => $boxes->count(),
            'boxes' => $boxes,
            'message' => 'Empty Middo Boxes stay with you until a rider collects them on the next delivery or pickup run.',
        ]);
    }

    public function packages(Request $request): JsonResponse
    {
        $items = MealPackage::query()
            ->published()
            ->withCount('days')
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now('Asia/Dhaka')->toDateString());
            })
            ->orderBy('display_order')
            ->orderBy('price_per_day')
            ->get()
            ->map(fn (MealPackage $package) => CorporateApiPresenter::mealPackage($package))
            ->values();

        $menus = MenuItem::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MenuItem $item) => CorporateApiPresenter::menuItem($item))
            ->values();

        return response()->json([
            'packages' => $items,
            'menus' => $menus,
            'weekday_labels' => PackageBilling::WEEKDAY_LABELS,
            'ordered_months' => PackageSubscription::orderedMonthsForUser((int) $request->user()->id),
        ]);
    }

    public function sendPackageOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g. 01710123456).',
        ]);

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

    public function packageShow(int $package): JsonResponse
    {
        $model = MealPackage::query()
            ->published()
            ->withCount('days')
            ->findOrFail($package);

        $menus = MenuItem::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MenuItem $item) => CorporateApiPresenter::menuItem($item))
            ->values();

        return response()->json([
            'package' => CorporateApiPresenter::mealPackage($model, withDays: false),
            'menus' => $menus,
            'weekday_labels' => PackageBilling::WEEKDAY_LABELS,
        ]);
    }

    public function packageQuote(Request $request, int $package): JsonResponse
    {
        $model = MealPackage::query()->published()->findOrFail($package);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CorporateOrderLimit::maxAllowed()],
            'target_month' => ['required', 'date_format:Y-m'],
            'omitted_weekdays' => ['nullable', 'array'],
            'omitted_weekdays.*' => ['integer', 'min:0', 'max:6'],
            'menu_selections' => ['required', 'array', 'min:1'],
            'menu_selections.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'menu_selections.*.day_count' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        try {
            $quote = PackageBilling::quoteFromSelections(
                $model,
                (int) $data['quantity'],
                $data['menu_selections'],
                PackageBilling::normalizeOmittedWeekdays($data['omitted_weekdays'] ?? []),
                $data['target_month']
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'menu_selections' => [$e->getMessage()],
            ]);
        }

        return response()->json([
            'package' => CorporateApiPresenter::mealPackage($model),
            'quote' => $quote,
            'balance' => (int) $request->user()->balance,
        ]);
    }

    public function subscribePackage(Request $request, int $package): JsonResponse
    {
        $model = MealPackage::query()->published()->findOrFail($package);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CorporateOrderLimit::maxAllowed()],
            'target_month' => ['required', 'date_format:Y-m'],
            'omitted_weekdays' => ['nullable', 'array'],
            'omitted_weekdays.*' => ['integer', 'min:0', 'max:6'],
            'menu_selections' => ['required', 'array', 'min:1'],
            'menu_selections.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'menu_selections.*.day_count' => ['required', 'integer', 'min:1', 'max:31'],
            'receiver_name' => ['required', 'string', 'min:2', 'max:120'],
            'receiver_mobile' => ['required', 'string', 'min:11', 'max:20'],
            'address' => ['required', 'string', 'min:5', 'max:500'],
            'city_id' => ['required', 'exists:cities,id'],
            'area_id' => ['required', 'exists:areas,id'],
            'delivery_time' => ['required', 'in:12:00 PM,11:30 AM'],
            'otp' => ['required', 'string', 'size:4'],
            'payment_method' => ['required', 'in:balance,gateway'],
            'payment_token' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);

        if (! OrderConfirmationOtp::verify($data['receiver_mobile'], $data['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired confirmation code.'],
            ]);
        }

        $omitted = PackageBilling::normalizeOmittedWeekdays($data['omitted_weekdays'] ?? []);

        try {
            $quote = PackageBilling::quoteFromSelections(
                $model,
                (int) $data['quantity'],
                $data['menu_selections'],
                $omitted,
                $data['target_month']
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'menu_selections' => [$e->getMessage()],
            ]);
        }

        if ($quote['billable_days'] !== $quote['available_days'] || $quote['available_days'] < 1) {
            try {
                PackageBilling::assertSelectionsFillMonth($quote);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'menu_selections' => [$e->getMessage()],
                ]);
            }
        }

        try {
            $result = app(PackageSubscriptionService::class)->subscribe(
                $request->user(),
                $model,
                (int) $data['quantity'],
                $omitted,
                $data['menu_selections'],
                $data['target_month'],
                $data['receiver_name'],
                $data['receiver_mobile'],
                $data['address'],
                (int) $data['city_id'],
                (int) $data['area_id'],
                $data['delivery_time'],
                $data['payment_method'],
                $data['payment_token'] ?? null,
                $data['coupon_code'] ?? null
            );
        } catch (\Throwable $e) {
            $field = str_contains(strtolower($e->getMessage()), 'already ordered a package')
                ? 'target_month'
                : 'payment_method';

            throw ValidationException::withMessages([
                $field => [$e->getMessage()],
            ]);
        }

        return response()->json([
            'message' => 'Package prepaid. Operations will assign exact delivery dates next.',
            'subscription' => CorporateApiPresenter::packageSubscription($result['subscription']),
            'balance' => (float) $request->user()->fresh()->balance,
        ], 201);
    }

    public function myPackages(Request $request): JsonResponse
    {
        $subs = PackageSubscription::query()
            ->forUser($request->user()->id)
            ->with(['package', 'orders.menuItem', 'selections.menuItem'])
            ->latest()
            ->get()
            ->map(fn (PackageSubscription $sub) => CorporateApiPresenter::packageSubscription($sub))
            ->values();

        return response()->json(['subscriptions' => $subs]);
    }

    public function myPackageShow(Request $request, int $subscription): JsonResponse
    {
        $sub = PackageSubscription::query()
            ->forUser($request->user()->id)
            ->with(['package', 'orders.menuItem', 'selections.menuItem'])
            ->findOrFail($subscription);

        return response()->json([
            'subscription' => CorporateApiPresenter::packageSubscription($sub),
        ]);
    }

    public function skipPackageDay(Request $request, int $order): JsonResponse
    {
        $model = Order::query()
            ->where('id', $order)
            ->where('user_id', $request->user()->id)
            ->whereNotNull('package_subscription_id')
            ->firstOrFail();

        try {
            $refund = (int) ($model->amount_paid ?: $model->total_amount);
            $updated = app(PackageSubscriptionService::class)->skipDay($request->user(), $model);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'order' => [$e->getMessage()],
            ]);
        }

        return response()->json([
            'message' => 'Day skipped. Amount credited to Middo Balance.',
            'refunded_amount' => $refund,
            'order' => CorporateApiPresenter::order($updated),
            'balance' => (float) $request->user()->fresh()->balance,
        ]);
    }
}
