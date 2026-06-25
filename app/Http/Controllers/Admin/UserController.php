<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // app/Http/Controllers/Admin/UserController.php
    public function index()
    {
        // Eager load role to avoid N+1 queries
        $users = \App\Models\User::with('role')->paginate(20); 
        return view('admin.users.index', compact('users'));
    }
    
}
