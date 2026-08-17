<?php

namespace App\Providers;

use App\Events\CommunityCommentCreated;
use App\Events\CommunityPostCreated;
use App\Events\CommunityPostLiked;
use App\Listeners\BroadcastCommunityEventListener;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('forgot', function (Request $request) {
            return Limit::perMinute(8)->by($request->ip());
        });

        Event::listen(CommunityPostCreated::class, [BroadcastCommunityEventListener::class, 'handle']);
        Event::listen(CommunityCommentCreated::class, [BroadcastCommunityEventListener::class, 'handle']);
        Event::listen(CommunityPostLiked::class, [BroadcastCommunityEventListener::class, 'handle']);
    }
}
