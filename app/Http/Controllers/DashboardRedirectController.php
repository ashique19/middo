<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class DashboardRedirectController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();

        // Check if user is active (your new field!)
        if ($user->status !== 'active') {
            $status = $user->status;
            Auth::logout();

            return redirect()
                ->route('login')
                ->with('account_status', $status);
        }

        return match($user->role->name) {
            'corporate' => redirect()->route('corporates.dashboard'),
            'kitchen'   => redirect()->route('kitchen.dashboard'),
            'delivery'  => redirect()->route('delivery.dashboard'),
            'operation'=> redirect()->route('operation.dashboard'),
            'admin'     => redirect()->route('admin.dashboard'),
            default     => redirect('/'),
        };
    }
}