<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // IMPORTANT: Check if user exists before calling methods on it
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized. Super admin access required.'], 403);
        }

        return $next($request);
    }
}
