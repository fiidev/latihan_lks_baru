<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): mixed
    {
        $allowed = explode(',', implode(',', $roles));
        $user = $request->user();

        if (! $user || ! in_array($user->role, $allowed)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        return $next($request);
    }
}
