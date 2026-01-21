<?php

use App\Enums\ScrapePhase;
use App\Enums\ScrapeRunStatus;
use App\Models\Platform;
use App\Models\ScrapeJob;
use App\Models\ScrapeRun;
use App\Models\SearchQuery;
use Illuminate\Database\Eloquent\Collection;

it('can create a scrape run', function () {
    $run = ScrapeRun::factory()->create();

    expect($run)->toBeInstanceOf(ScrapeRun::class)
        ->and($run->id)->not->toBeNull();
});

it('belongs to a platform', function () {
    $platform = Platform::factory()->create();
    $searchQuery = SearchQuery::factory()->forPlatform($platform)->create();
    $run = ScrapeRun::factory()->forSearchQuery($searchQuery)->create();

    expect($run->platform)->toBeInstanceOf(Platform::class)
        ->and($run->platform->id)->toBe($platform->id);
});

it('belongs to a search query', function () {
    $searchQuery = SearchQuery::factory()->create();
    $run = ScrapeRun::factory()->forSearchQuery($searchQuery)->create();

    expect($run->searchQuery)->toBeInstanceOf(SearchQuery::class)
        ->and($run->searchQuery->id)->toBe($searchQuery->id);
});

it('has many scrape jobs', function () {
    $run = ScrapeRun::factory()->create();
    $job = ScrapeJob::factory()->forRun($run)->create();

    expect($run->scrapeJobs)->toBeInstanceOf(Collection::class)
        ->and($run->scrapeJobs)->toHaveCount(1)
        ->and($run->scrapeJobs->first()->id)->toBe($job->id);
});

it('casts status to enum', function () {
    $run = ScrapeRun::factory()->create(['status' => ScrapeRunStatus::Discovering]);
    $run->refresh();

    expect($run->status)->toBeInstanceOf(ScrapeRunStatus::class)
        ->and($run->status)->toBe(ScrapeRunStatus::Discovering);
});

it('casts phase to enum', function () {
    $run = ScrapeRun::factory()->create(['phase' => ScrapePhase::Scrape]);
    $run->refresh();

    expect($run->phase)->toBeInstanceOf(ScrapePhase::class)
        ->and($run->phase)->toBe(ScrapePhase::Scrape);
});

it('casts stats to array', function () {
    $stats = ['pages_total' => 10, 'pages_done' => 5];
    $run = ScrapeRun::factory()->create(['stats' => $stats]);
    $run->refresh();

    expect($run->stats)->toBeArray()
        ->and($run->stats['pages_total'])->toBe(10)
        ->and($run->stats['pages_done'])->toBe(5);
});

it('can be created with discovering state', function () {
    $run = ScrapeRun::factory()->discovering()->create();

    expect($run->status)->toBe(ScrapeRunStatus::Discovering)
        ->and($run->phase)->toBe(ScrapePhase::Discover)
        ->and($run->started_at)->not->toBeNull();
});

it('can be created with scraping state', function () {
    $run = ScrapeRun::factory()->scraping()->create();

    expect($run->status)->toBe(ScrapeRunStatus::Scraping)
        ->and($run->phase)->toBe(ScrapePhase::Scrape);
});

it('can be created with completed state', function () {
    $run = ScrapeRun::factory()->completed()->create();

    expect($run->status)->toBe(ScrapeRunStatus::Completed)
        ->and($run->completed_at)->not->toBeNull();
});

it('can be created with failed state', function () {
    $run = ScrapeRun::factory()->failed()->create();

    expect($run->status)->toBe(ScrapeRunStatus::Failed)
        ->and($run->error_message)->not->toBeNull();
});

it('has many discovered listings', function () {
    $run = ScrapeRun::factory()->create();
    $listing = \App\Models\DiscoveredListing::factory()->create([
        'platform_id' => $run->platform_id,
        'scrape_run_id' => $run->id,
    ]);

    expect($run->discoveredListings)->toBeInstanceOf(Collection::class)
        ->and($run->discoveredListings)->toHaveCount(1)
        ->and($run->discoveredListings->first()->id)->toBe($listing->id);
});

it('computes stats from actual records', function () {
    $run = ScrapeRun::factory()->create();

    // Create discovery jobs (pages_total is now computed from discovery job count)
    ScrapeJob::factory()->forRun($run)->discovery()->completed()->count(3)->create();
    ScrapeJob::factory()->forRun($run)->discovery()->failed()->count(1)->create();
    ScrapeJob::factory()->forRun($run)->discovery()->running()->create();

    // Create discovered listings with various statuses
    \App\Models\DiscoveredListing::factory()->count(10)->create([
        'platform_id' => $run->platform_id,
        'scrape_run_id' => $run->id,
        'status' => \App\Enums\DiscoveredListingStatus::Pending,
    ]);
    \App\Models\DiscoveredListing::factory()->scraped()->count(7)->create([
        'platform_id' => $run->platform_id,
        'scrape_run_id' => $run->id,
    ]);
    \App\Models\DiscoveredListing::factory()->failed()->count(2)->create([
        'platform_id' => $run->platform_id,
        'scrape_run_id' => $run->id,
    ]);

    $stats = $run->computeStats();

    expect($stats['pages_total'])->toBe(5) // Count of all discovery jobs (3 completed + 1 failed + 1 running)
        ->and($stats['pages_done'])->toBe(3) // 3 completed discovery jobs
        ->and($stats['pages_failed'])->toBe(1) // 1 failed discovery job
        ->and($stats['listings_found'])->toBe(19) // 10 + 7 + 2 total
        ->and($stats['listings_scraped'])->toBe(7) // 7 scraped
        ->and($stats['listings_failed'])->toBe(2); // 2 failed
});

it('computes pages_total from discovery jobs count', function () {
    $run = ScrapeRun::factory()->create();

    // Create 4 discovery jobs - pages_total is always computed from job count
    ScrapeJob::factory()->forRun($run)->discovery()->completed()->count(4)->create();

    $stats = $run->computeStats();

    // pages_total is count of all discovery jobs
    expect($stats['pages_total'])->toBe(4);
});

it('returns zero stats when no records exist', function () {
    $run = ScrapeRun::factory()->create([
        'stats' => [],
    ]);

    $stats = $run->computeStats();

    expect($stats['pages_total'])->toBe(0)
        ->and($stats['pages_done'])->toBe(0)
        ->and($stats['pages_failed'])->toBe(0)
        ->and($stats['listings_found'])->toBe(0)
        ->and($stats['listings_scraped'])->toBe(0)
        ->and($stats['listings_failed'])->toBe(0);
});

it('only counts discovery jobs for page stats not listing jobs', function () {
    $run = ScrapeRun::factory()->create();

    // Create discovery jobs
    ScrapeJob::factory()->forRun($run)->discovery()->completed()->count(2)->create();

    // Create listing jobs (should NOT be counted in page stats)
    ScrapeJob::factory()->forRun($run)->listing()->completed()->count(5)->create();

    $stats = $run->computeStats();

    expect($stats['pages_done'])->toBe(2) // Only discovery jobs
        ->and($stats['pages_total'])->toBe(2); // Only discovery jobs count
});
