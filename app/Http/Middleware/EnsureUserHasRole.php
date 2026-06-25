<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if ($user && in_array($role, ['corporate', 'kitchen', 'delivery'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'error' => 'Login as '.ucfirst($role).' to continue.',
            ]);
        }

        abort(403, 'You do not have access to this section.');
    }
}