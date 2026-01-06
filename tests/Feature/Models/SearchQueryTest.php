<?php

use App\Models\Platform;
use App\Models\ScrapeRun;
use App\Models\SearchQuery;
use Illuminate\Database\Eloquent\Collection;

it('can create a search query', function () {
    $searchQuery = SearchQuery::factory()->create();

    expect($searchQuery)->toBeInstanceOf(SearchQuery::class)
        ->and($searchQuery->id)->not->toBeNull()
        ->and($searchQuery->name)->not->toBeEmpty()
        ->and($searchQuery->search_url)->not->toBeEmpty();
});

it('belongs to a platform', function () {
    $platform = Platform::factory()->create();
    $searchQuery = SearchQuery::factory()->forPlatform($platform)->create();

    expect($searchQuery->platform)->toBeInstanceOf(Platform::class)
        ->and($searchQuery->platform->id)->toBe($platform->id);
});

it('has many scrape runs', function () {
    $searchQuery = SearchQuery::factory()->create();
    $run = ScrapeRun::factory()->forSearchQuery($searchQuery)->create();

    expect($searchQuery->scrapeRuns)->toBeInstanceOf(Collection::class)
        ->and($searchQuery->scrapeRuns)->toHaveCount(1)
        ->and($searchQuery->scrapeRuns->first()->id)->toBe($run->id);
});

it('casts is_active to boolean', function () {
    $searchQuery = SearchQuery::factory()->create(['is_active' => 1]);
    $searchQuery->refresh();

    expect($searchQuery->is_active)->toBeBool()
        ->and($searchQuery->is_active)->toBeTrue();
});

it('casts last_run_at to datetime', function () {
    $searchQuery = SearchQuery::factory()->withLastRun()->create();
    $searchQuery->refresh();

    expect($searchQuery->last_run_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('can be marked as inactive', function () {
    $searchQuery = SearchQuery::factory()->inactive()->create();

    expect($searchQuery->is_active)->toBeFalse();
});
