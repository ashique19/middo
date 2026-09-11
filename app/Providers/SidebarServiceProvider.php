<?php

namespace App\Providers;

use App\Models\Nav;
use App\Support\StaffNavSync;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class SidebarServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer(
            ['components.layouts.private.sidebar'],
            function ($view) {
                if (! Auth::check()) {
                    return;
                }

                $user = Auth::user();
                $roleId = $user->role_id; // Use direct property from your User model

                if (! $roleId) {
                    $view->with('navs', collect());

                    return;
                }

                // Query using direct role_id foreign key — never surface Alerts here
                // (those pages are reached from the top-bar notification bell).
                $navs = Nav::where('role_id', $roleId)
                    ->whereNull('parent_id') // Get section parents (and any legacy flat links)
                    ->where(function ($query) {
                        $query->whereNull('route_name')
                            ->orWhereNotIn('route_name', StaffNavSync::ALERT_ROUTE_NAMES);
                    })
                    ->where('title', '!=', 'Alerts')
                    ->with(['children' => function ($query) use ($roleId) {
                        $query->where('role_id', $roleId)
                            ->where(function ($childQuery) {
                                $childQuery->whereNull('route_name')
                                    ->orWhereNotIn('route_name', StaffNavSync::ALERT_ROUTE_NAMES);
                            })
                            ->where('title', '!=', 'Alerts')
                            ->orderBy('order');
                    }])
                    ->orderBy('order')
                    ->get();

                $view->with('navs', $navs);
            }
        );
    }
}
