<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Nav;

class SidebarServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer(
            ['components.layouts.private.sidebar'], 
            function ($view) {
                if (Auth::check()) {
                    $user = Auth::user();
                    $roleId = $user->role_id; // Use direct property from your User model

                    if ($roleId) {
                        // Query using direct role_id foreign key
                        $navs = Nav::where('role_id', $roleId)
                            ->whereNull('parent_id') // Get parents
                            ->with(['children' => function ($query) use ($roleId) {
                                $query->where('role_id', $roleId); // Ensure children share same role
                            }])
                            ->orderBy('order')
                            ->get();
                        
                        $view->with('navs', $navs);
                    } else {
                        $view->with('navs', collect());
                    }
                }
            }
        );
    }
}