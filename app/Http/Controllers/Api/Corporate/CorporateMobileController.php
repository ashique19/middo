<?php

namespace App\Http\Controllers\Api\Corporate;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\User;
use App\Support\CorporateApiPresenter;
use App\Support\CorporateOrderLimit;
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

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => CorporateApiPresenter::user($request->user()),
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

    public function placeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'delivery_time' => ['nullable', 'string', 'max:40'],
            'dates' => ['required', 'array', 'min:1'],
            'dates.*.date' => ['required', 'date_format:Y-m-d'],
            'dates.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $menuItem = MenuItem::query()->findOrFail($data['menu_item_id']);
        $deliveryTime = $data['delivery_time'] ?? '12:00 PM';

        $created = [];

        DB::transaction(function () use ($data, $user, $menuItem, $deliveryTime, &$created) {
            $presented = CorporateApiPresenter::user($user);
            $addressParts = array_filter([
                $user->address,
                $presented['area'] ?? null,
                $presented['city'] ?? null,
            ]);
            $fullAddress = $addressParts !== []
                ? implode(', ', $addressParts)
                : 'Corporate delivery address on file';

            foreach ($data['dates'] as $line) {
                $date = $line['date'];
                $qty = (int) $line['quantity'];

                if (CorporateOrderLimit::exceedsDailyLimit($user->id, $date, $qty)) {
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
        });

        return response()->json([
            'message' => 'Your meal track has been scheduled successfully.',
            'orders' => $created,
        ], 201);
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
