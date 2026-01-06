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
