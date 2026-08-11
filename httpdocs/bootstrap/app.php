<?php

use App\Http\Middleware\ApiAuthLog;
use App\Http\Middleware\ForceJson;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;
use App\Http\Middleware\AdminSuper;
use Illuminate\Http\Exceptions\MalformedUrlException;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php', // ✅
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'force.json' => ForceJson::class,
            'set.locale' => SetLocale::class,
            'role.approved' => \App\Http\Middleware\EnsureRoleApproved::class,
            'admin.super' => AdminSuper::class,
        ]);
        // Force JSON Accept so unauthenticated API never redirects to missing web "login" route.
        $middleware->prependToGroup('api', ForceJson::class);
        $middleware->appendToGroup('api', ApiAuthLog::class);
        $middleware->appendToGroup('api', SecurityHeaders::class);
        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->redirectGuestsTo(fn () => '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->guest('/');
        });
        $exceptions->renderable(function (MalformedUrlException $e, $request) {
            Log::warning('malformed_url', [
                'path' => $request->getPathInfo(),
                'uri' => $request->getRequestUri(),
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);
            return response()->json(['message' => 'Malformed URL'], 400);
        });
    })
    ->withBroadcasting(__DIR__.'/../routes/channels.php') // ✅ هذا هو الشكل الصحيح
    ->create();
