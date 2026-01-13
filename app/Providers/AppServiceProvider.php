<?php

namespace App\Providers;

use App\Events\DiscoveryCompleted;
use App\Events\ScrapingCompleted;
use App\Listeners\MarkRunCompletedListener;
use App\Listeners\TransitionToScrapingListener;
use Illuminate\Cache\RateLimiting\Limit;
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
        Event::listen(DiscoveryCompleted::class, TransitionToScrapingListener::class);
        Event::listen(ScrapingCompleted::class, MarkRunCompletedListener::class);

        // Rate limiter for Claude API calls (10k input tokens/min ≈ 3-4 requests/min)
        // Using 3 requests per minute to stay safely under limit
        RateLimiter::for('claude-api', function (object $job) {
            return Limit::perMinute(3)->by('claude-api');
        });
    }
}
