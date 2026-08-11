<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSuper
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // If not authenticated as admin, block access
        if (!$user || strcasecmp($user->role ?? '', 'admin') !== 0) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
