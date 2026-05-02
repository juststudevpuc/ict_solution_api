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

        if(!auth()->check() || auth()->user()->role !== "admin"){
            return response()->json([
                "message" => "Access Denied. Admin Only!"
            ],403);
        }
        return $next($request);
    }
}
