<?php

namespace App\Http\Controllers\Corporates;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    
    public function index(Request $request)
    {
        $user = $request->user();
        
        $activeOrdersCount = 0; // $user->corporate->orders()->where('status', 'active')->count();

        return view('corporates.dashboard', compact('activeOrdersCount'));
    }

}
