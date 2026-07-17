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

        if ($user && $user->role && $user->role->name === $role) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'You do not have access to this section.',
            ], 403);
        }

        // Wrong role while authenticated: send them to their own portal (do not log them out).
        if ($user) {
            return redirect()
                ->route('dashboard.redirect')
                ->with('error', 'You do not have access to that section.');
        }

        return redirect()->route('login');
    }
}
