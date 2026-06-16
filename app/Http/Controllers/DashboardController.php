<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return match($user->role->name) {
            'corporate' => redirect()->route('corporates.dashboard'),
            'kitchen'   => redirect()->route('kitchen.dashboard'),
            default     => abort(403, 'Unauthorized access'),
        };
    }
}