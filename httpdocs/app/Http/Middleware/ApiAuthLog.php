<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        $token = null;
        $scheme = null;

        if ($authHeader) {
            $parts = explode(' ', $authHeader, 2);
            $scheme = $parts[0] ?? null;
            $token = $parts[1] ?? null;
        }

        $tokenRecord = null;
        if ($token) {
            try {
                $pat = PersonalAccessToken::findToken(trim($token));
                if ($pat) {
                    $tokenRecord = [
                        'id' => $pat->id,
                        'tokenable_type' => $pat->tokenable_type,
                        'tokenable_id' => $pat->tokenable_id,
                        'name' => $pat->name,
                        'abilities' => $pat->abilities,
                        'last_used_at' => $pat->last_used_at ? $pat->last_used_at->toDateTimeString() : null,
                        'expires_at' => $pat->expires_at ? $pat->expires_at->toDateTimeString() : null,
                        'created_at' => $pat->created_at ? $pat->created_at->toDateTimeString() : null,
                    ];
                }
            } catch (\Throwable $e) {
                $tokenRecord = ['error' => 'token_lookup_failed'];
            }
        }

        $response = $next($request);

        Log::channel('api_auth')->info('api.auth', [
            'ip' => $request->ip(),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'status' => $response->getStatusCode(),
            'ua' => $request->userAgent(),
            'has_auth' => $authHeader !== null,
            'scheme' => $scheme,
            'token_len' => $token ? strlen(trim($token)) : 0,
            'token_sha256' => $token ? hash('sha256', trim($token)) : null,
            'token_record' => $tokenRecord,
        ]);

        return $response;
    }
}
