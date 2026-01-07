<?php

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapePhase;
use App\Enums\ScrapeRunStatus;
use App\Events\ScrapeRunProgress;
use App\Jobs\DiscoverSearchJob;
use App\Jobs\ScrapeListingJob;
use App\Models\DiscoveredListing;
use App\Models\ScrapeRun;
use App\Models\SearchQuery;
use App\Services\ScrapeOrchestrator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Event::fake([ScrapeRunProgress::class]);
});

it('starts a run and dispatches discovery job', function () {
    $searchQuery = SearchQuery::factory()->create();
    $orchestrator = app(ScrapeOrchestrator::class);

    $run = $orchestrator->startRun($searchQuery);

    expect($run)->toBeInstanceOf(ScrapeRun::class)
        ->and($run->status)->toBe(ScrapeRunStatus::Discovering)
        ->and($run->phase)->toBe(ScrapePhase::Discover)
        ->and($run->started_at)->not->toBeNull()
        ->and($run->stats)->toBeArray();

    Queue::assertPushed(DiscoverSearchJob::class, function ($job) use ($run, $searchQuery) {
        return $job->platformId === $run->platform_id
            && $job->searchUrl === $searchQuery->search_url
            && $job->scrapeRunId === $run->id;
    });
});

it('updates search query last_run_at when starting a run', function () {
    $searchQuery = SearchQuery::factory()->create(['last_run_at' => null]);
    $orchestrator = app(ScrapeOrchestrator::class);

    $orchestrator->startRun($searchQuery);

    $searchQuery->refresh();
    expect($searchQuery->last_run_at)->not->toBeNull();
});

it('transitions to scraping phase and dispatches listing jobs', function () {
    $run = ScrapeRun::factory()->discovering()->create();
    $listings = DiscoveredListing::factory()
        ->count(3)
        ->for($run->platform)
        ->create([
            'status' => DiscoveredListingStatus::Pending,
            'scrape_run_id' => $run->id,
        ]);

    $orchestrator = app(ScrapeOrchestrator::class);
    $orchestrator->transitionToScraping($run);

    $run->refresh();
    expect($run->status)->toBe(ScrapeRunStatus::Scraping)
        ->and($run->phase)->toBe(ScrapePhase::Scrape);

    Queue::assertPushed(ScrapeListingJob::class, 3);

    // Verify listings were marked as queued
    foreach ($listings as $listing) {
        expect($listing->fresh()->status)->toBe(DiscoveredListingStatus::Queued);
    }

    Event::assertDispatched(ScrapeRunProgress::class, function ($event) use ($run) {
        return $event->run->id === $run->id && $event->type === 'phase_changed';
    });
});

it('marks a run as completed', function () {
    $run = ScrapeRun::factory()->scraping()->create();

    $orchestrator = app(ScrapeOrchestrator::class);
    $orchestrator->markCompleted($run);

    $run->refresh();
    expect($run->status)->toBe(ScrapeRunStatus::Completed)
        ->and($run->completed_at)->not->toBeNull();

    Event::assertDispatched(ScrapeRunProgress::class, function ($event) use ($run) {
        return $event->run->id === $run->id && $event->type === 'completed';
    });
});

it('marks a run as failed with error message', function () {
    $run = ScrapeRun::factory()->discovering()->create();

    $orchestrator = app(ScrapeOrchestrator::class);
    $orchestrator->markFailed($run, 'Connection timeout');

    $run->refresh();
    expect($run->status)->toBe(ScrapeRunStatus::Failed)
        ->and($run->error_message)->toBe('Connection timeout')
        ->and($run->completed_at)->not->toBeNull();

    Event::assertDispatched(ScrapeRunProgress::class, function ($event) use ($run) {
        return $event->run->id === $run->id && $event->type === 'failed';
    });
});

it('updates stats and dispatches progress event', function () {
    $run = ScrapeRun::factory()->discovering()->create([
        'stats' => ['pages_total' => 10, 'pages_done' => 0],
    ]);

    $orchestrator = app(ScrapeOrchestrator::class);
    $orchestrator->updateStats($run, ['pages_done' => 5]);

    $run->refresh();
    expect($run->stats['pages_total'])->toBe(10)
        ->and($run->stats['pages_done'])->toBe(5);

    Event::assertDispatched(ScrapeRunProgress::class, function ($event) use ($run) {
        return $event->run->id === $run->id && $event->type === 'stats_updated';
    });
});

it('checks discovery complete when all pages done', function () {
    $run = ScrapeRun::factory()->create([
        'stats' => ['pages_total' => 10, 'pages_done' => 10],
    ]);

    $orchestrator = app(ScrapeOrchestrator::class);

    expect($orchestrator->checkDiscoveryComplete($run))->toBeTrue();
});

it('checks discovery incomplete when pages remaining', function () {
    $run = ScrapeRun::factory()->create([
        'stats' => ['pages_total' => 10, 'pages_done' => 5],
    ]);

    $orchestrator = app(ScrapeOrchestrator::class);

    expect($orchestrator->checkDiscoveryComplete($run))->toBeFalse();
});

it('checks scraping complete when all listings scraped', function () {
    $run = ScrapeRun::factory()->create([
        'stats' => ['listings_found' => 100, 'listings_scraped' => 100],
    ]);

    $orchestrator = app(ScrapeOrchestrator::class);

    expect($orchestrator->checkScrapingComplete($run))->toBeTrue();
});

it('checks scraping incomplete when listings remaining', function () {
    $run = ScrapeRun::factory()->create([
        'stats' => ['listings_found' => 100, 'listings_scraped' => 50],
    ]);

    $orchestrator = app(ScrapeOrchestrator::class);

    expect($orchestrator->checkScrapingComplete($run))->toBeFalse();
});
