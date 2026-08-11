<?php

namespace App\Http\Middleware;

use Closure;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $locale = $request->header('Accept-Language', 'ar');
        app()->setLocale(in_array($locale, ['ar', 'en', 'tr']) ? $locale : 'ar');

        return $next($request);
    }
}
