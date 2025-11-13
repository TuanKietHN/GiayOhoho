<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $has = $user->roles()->where('name', $role)->exists();
        if (! $has) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return $next($request);
    }
}

