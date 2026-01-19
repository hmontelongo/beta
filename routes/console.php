<?php

use App\Jobs\ProcessDeduplicationBatchJob;
use App\Jobs\ProcessGeocodingBatchJob;
use App\Jobs\ProcessPropertyCreationBatchJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reset stale processing jobs every 5 minutes
Schedule::command('listings:reset-stale')->everyFiveMinutes();

// Scheduled Scraping: Check and run due scrapes every 5 minutes
Schedule::command('scrape:run-scheduled')->everyFiveMinutes();

// Pipeline jobs run every minute, offset from each other in sequence order
// withoutOverlapping prevents pile-up, jobs exit quickly if nothing to do

// Step 1: Geocoding - runs at :00, :01, :02... (every minute)
Schedule::job(new ProcessGeocodingBatchJob)->everyMinute()->withoutOverlapping();

// Step 2: Deduplication - runs at :00, :01, :02... (every minute, after geocoding via queue priority)
Schedule::job(new ProcessDeduplicationBatchJob)->everyMinute()->withoutOverlapping();

// Step 3: Property Creation - runs at :00, :01, :02... (every minute, after dedup via queue priority)
Schedule::job(new ProcessPropertyCreationBatchJob)->everyMinute()->withoutOverlapping();
