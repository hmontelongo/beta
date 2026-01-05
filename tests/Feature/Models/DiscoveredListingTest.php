<?php

use App\Enums\DiscoveredListingStatus;
use App\Models\DiscoveredListing;
use App\Models\Listing;
use App\Models\Platform;
use App\Models\ScrapeJob;

it('can create a discovered listing', function () {
    $discoveredListing = DiscoveredListing::factory()->create();

    expect($discoveredListing)->toBeInstanceOf(DiscoveredListing::class)
        ->and($discoveredListing->id)->not->toBeNull()
        ->and($discoveredListing->url)->not->toBeEmpty();
});

it('belongs to a platform', function () {
    $platform = Platform::factory()->create();
    $discoveredListing = DiscoveredListing::factory()->for($platform)->create();

    expect($discoveredListing->platform)->toBeInstanceOf(Platform::class)
        ->and($discoveredListing->platform->id)->toBe($platform->id);
});

it('casts status to enum', function () {
    $discoveredListing = DiscoveredListing::factory()->create(['status' => DiscoveredListingStatus::Queued]);

    $discoveredListing->refresh();

    expect($discoveredListing->status)->toBeInstanceOf(DiscoveredListingStatus::class)
        ->and($discoveredListing->status)->toBe(DiscoveredListingStatus::Queued);
});

it('defaults to Pending status', function () {
    $discoveredListing = DiscoveredListing::factory()->create();

    expect($discoveredListing->status)->toBe(DiscoveredListingStatus::Pending);
});

it('can be created as queued', function () {
    $discoveredListing = DiscoveredListing::factory()->queued()->create();

    expect($discoveredListing->status)->toBe(DiscoveredListingStatus::Queued);
});

it('can be created as scraped', function () {
    $discoveredListing = DiscoveredListing::factory()->scraped()->create();

    expect($discoveredListing->status)->toBe(DiscoveredListingStatus::Scraped)
        ->and($discoveredListing->attempts)->toBe(1)
        ->and($discoveredListing->last_attempt_at)->not->toBeNull();
});

it('can be created as failed', function () {
    $discoveredListing = DiscoveredListing::factory()->failed()->create();

    expect($discoveredListing->status)->toBe(DiscoveredListingStatus::Failed)
        ->and($discoveredListing->attempts)->toBeGreaterThan(0)
        ->and($discoveredListing->last_attempt_at)->not->toBeNull();
});

it('can be created as skipped', function () {
    $discoveredListing = DiscoveredListing::factory()->skipped()->create();

    expect($discoveredListing->status)->toBe(DiscoveredListingStatus::Skipped);
});

it('can be created with batch id', function () {
    $batchId = 'test-batch-123';
    $discoveredListing = DiscoveredListing::factory()->withBatch($batchId)->create();

    expect($discoveredListing->batch_id)->toBe($batchId);
});

it('can be created with high priority', function () {
    $discoveredListing = DiscoveredListing::factory()->highPriority()->create();

    expect($discoveredListing->priority)->toBe(10);
});

it('has listing relationship', function () {
    $discoveredListing = DiscoveredListing::factory()->create();
    $listing = Listing::factory()->create(['discovered_listing_id' => $discoveredListing->id]);

    expect($discoveredListing->listing)->toBeInstanceOf(Listing::class)
        ->and($discoveredListing->listing->id)->toBe($listing->id);
});

it('has scrape jobs relationship', function () {
    $discoveredListing = DiscoveredListing::factory()->create();
    $scrapeJob = ScrapeJob::factory()->create(['discovered_listing_id' => $discoveredListing->id]);

    expect($discoveredListing->scrapeJobs)->toHaveCount(1)
        ->and($discoveredListing->scrapeJobs->first()->id)->toBe($scrapeJob->id);
});

it('enforces unique url per platform', function () {
    $platform = Platform::factory()->create();
    $url = 'https://example.com/listing/123';

    DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'url' => $url,
    ]);

    expect(fn () => DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'url' => $url,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows same url on different platforms', function () {
    $platform1 = Platform::factory()->create();
    $platform2 = Platform::factory()->create();
    $url = 'https://example.com/listing/123';

    $listing1 = DiscoveredListing::factory()->create([
        'platform_id' => $platform1->id,
        'url' => $url,
    ]);

    $listing2 = DiscoveredListing::factory()->create([
        'platform_id' => $platform2->id,
        'url' => $url,
    ]);

    expect($listing1->id)->not->toBe($listing2->id);
});

it('casts last_attempt_at to datetime', function () {
    $discoveredListing = DiscoveredListing::factory()->scraped()->create();

    expect($discoveredListing->last_attempt_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('allows nullable external_id', function () {
    $discoveredListing = DiscoveredListing::factory()->create(['external_id' => null]);

    expect($discoveredListing->external_id)->toBeNull();
});
