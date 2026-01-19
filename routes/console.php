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

// Geocoding: Convert addresses to coordinates every 5 minutes (before dedup)
Schedule::job(new ProcessGeocodingBatchJob)->everyFiveMinutes()->withoutOverlapping();

// Deduplication: Process geocoded listings and create groups every 10 minutes
Schedule::job(new ProcessDeduplicationBatchJob)->everyTenMinutes()->withoutOverlapping();

// Property Creation: Process approved listing groups with AI every 10 minutes
Schedule::job(new ProcessPropertyCreationBatchJob)->everyTenMinutes()->withoutOverlapping();
