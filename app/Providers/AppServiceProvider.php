<?php

namespace App\Providers;

use App\Events\DiscoveryCompleted;
use App\Events\ScrapingCompleted;
use App\Listeners\MarkRunCompletedListener;
use App\Listeners\TransitionToScrapingListener;
use Illuminate\Support\Facades\Event;
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
    }
}
