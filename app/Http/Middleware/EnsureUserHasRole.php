<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        // 1. Check if user is logged in
        // 2. Check the relationship (role) and compare the 'name' column
        if ($user && $user->role && $user->role->name === $role) {
            return $next($request);
        }

        // Return 403 or redirect
        abort(403, 'You do not have access to this section.');
    }
}