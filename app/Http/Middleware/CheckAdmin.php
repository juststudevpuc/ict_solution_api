<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if user is logged in
        if (!auth()->check()) {
            return response()->json([
                "message" => "Unauthenticated."
            ], 401);
        }

        // 2. Allow BOTH 'admin' and 'staff' to access the backend API routes
        $role = strtolower(auth()->user()->role);

        if (!in_array($role, ['admin', 'staff'])) {
            return response()->json([
                "message" => "Access Denied. Staff or Admin privileges required!"
            ], 403);
        }

        return $next($request);
    }
}
