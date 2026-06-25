<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;

class NavRoleController extends Controller
{
    /**
     * Display the Nav-Role Management Page.
     */
    public function index()
    {
        // Fetch ONLY admin and operation roles, while eager loading their ordered navigation items
        $roles = Role::whereIn('name', ['admin', 'operation']) // Use 'name' instead of 'slug' if your column is named differently
            ->with(['navs' => function($query) {
                $query->orderBy('order');
            }])
            ->get();

        return view('admin.navs.index', compact('roles'));
    }

    
}