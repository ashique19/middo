<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Check if user is authenticated and possesses the required permission
        if ($request->user() && $request->user()->hasPermission($permission)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthorized action.',
            ], 403);
        }

        abort(403, 'Unauthorized action.');
    }
}
