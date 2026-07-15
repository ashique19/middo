<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderPaymentController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $order->load('menuItem');

        return view('public.order-payment', [
            'order' => $order,
        ]);
    }
}
