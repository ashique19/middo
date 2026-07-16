<?php

namespace App\Http\Controllers\Api\Corporate;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stub API for the Middo Corporate Flutter app.
 * Replace mock payloads with Eloquent queries + Sanctum tokens.
 */
class CorporateMobileController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        return response()->json([
            'message' => 'Install Laravel Sanctum and issue a token for corporate users.',
            'user' => [
                'company_name' => 'Acme BD Ltd',
                'email' => $request->string('email')->toString(),
                'balance' => 12450,
                'area' => 'Gulshan 1',
            ],
        ], 501);
    }

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'metrics' => [
                'active_orders' => 3,
                'next_meal' => '12:00 PM',
                'next_delivery_hint' => 'Delivery 11:30',
                'monthly_spend' => 48200,
                'monthly_saved' => 4820,
            ],
        ]);
    }

    public function menu(): JsonResponse
    {
        return response()->json([
            'items' => [
                [
                    'id' => 'm2',
                    'name' => 'Beef Tehari Combo',
                    'description' => 'Fragrant tehari, salad, raita & dessert',
                    'price' => 420,
                    'thumbnail' => '/img/menu/menu-2.jpg',
                    'tags' => ['Thalis', 'Protein'],
                ],
            ],
        ]);
    }

    public function scheduled(): JsonResponse
    {
        return response()->json(['orders' => []]);
    }

    public function history(): JsonResponse
    {
        return response()->json(['orders' => []]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $request->validate([
            'menu_item_id' => ['required'],
            'dates' => ['required', 'array'],
        ]);

        return response()->json([
            'message' => 'Order placement stub — connect to existing corporate checkout flow.',
        ], 501);
    }

    public function track(int $order): JsonResponse
    {
        return response()->json([
            'order_id' => $order,
            'events' => [],
        ]);
    }

    public function supportThread(int $order): JsonResponse
    {
        return response()->json([
            'order_id' => $order,
            'messages' => [],
        ]);
    }

    public function supportMessage(Request $request, int $order): JsonResponse
    {
        $request->validate(['message' => ['required', 'string']]);

        return response()->json([
            'order_id' => $order,
            'message' => 'Support message stub.',
        ], 501);
    }

    public function topUp(Request $request): JsonResponse
    {
        $request->validate(['amount' => ['required', 'numeric', 'min:1']]);

        return response()->json([
            'message' => 'Wallet top-up stub.',
        ], 501);
    }
}
