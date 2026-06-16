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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Check if user is authenticated and possesses the required permission
        if ($request->user() && $request->user()->hasPermission($permission)) {
            return $next($request);
        }

        // Return 403 Forbidden if the permission check fails
        abort(403, 'Unauthorized action.');
    }
}