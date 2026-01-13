<?php

use App\Jobs\ProcessAiEnrichmentBatchJob;
use App\Jobs\ProcessDeduplicationBatchJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reset stale processing jobs every 5 minutes
Schedule::command('listings:reset-stale')->everyFiveMinutes();

// AI Enrichment: Process pending listings every 15 minutes
Schedule::job(new ProcessAiEnrichmentBatchJob)->everyFifteenMinutes();

// Deduplication: Process enriched listings every 30 minutes
Schedule::job(new ProcessDeduplicationBatchJob)->everyThirtyMinutes();
