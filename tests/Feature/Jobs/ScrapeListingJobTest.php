<?php

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Jobs\ScrapeListingJob;
use App\Models\DiscoveredListing;
use App\Models\Listing;
use App\Models\Platform;
use App\Models\ScrapeJob;
use App\Services\ScrapeOrchestrator;
use App\Services\ScraperService;

it('updates discovered listing status to queued', function () {
    $platform = Platform::factory()->create();
    $discoveredListing = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'status' => DiscoveredListingStatus::Pending,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('scrapeListing')
        ->andReturn([
            'external_id' => 'abc123',
            'operations' => [['type' => 'rent', 'price' => 15000]],
        ]);

    $job = new ScrapeListingJob($discoveredListing->id);
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    $discoveredListing->refresh();
    expect($discoveredListing->status)->toBe(DiscoveredListingStatus::Scraped);
});

it('creates a scrape job with listing type', function () {
    $platform = Platform::factory()->create();
    $discoveredListing = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('scrapeListing')
        ->andReturn([
            'external_id' => 'abc123',
            'operations' => [],
        ]);

    $job = new ScrapeListingJob($discoveredListing->id);
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    $scrapeJob = ScrapeJob::where('discovered_listing_id', $discoveredListing->id)->first();
    expect($scrapeJob)->not->toBeNull()
        ->and($scrapeJob->job_type)->toBe(ScrapeJobType::Listing)
        ->and($scrapeJob->status)->toBe(ScrapeJobStatus::Completed)
        ->and($scrapeJob->platform_id)->toBe($platform->id);
});

it('creates a listing record from scraped data', function () {
    $platform = Platform::factory()->create();
    $discoveredListing = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'url' => 'https://example.com/listing/123',
        'external_id' => 'ext-123',
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('scrapeListing')
        ->andReturn([
            'external_id' => 'scraped-123',
            'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']],
            'external_codes' => ['easybroker' => 'EB-12345'],
            'data_quality' => ['confirmed' => ['price']],
        ]);

    $job = new ScrapeListingJob($discoveredListing->id);
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    expect(Listing::count())->toBe(1);

    $listing = Listing::first();
    expect($listing->platform_id)->toBe($platform->id)
        ->and($listing->discovered_listing_id)->toBe($discoveredListing->id)
        ->and($listing->external_id)->toBe('scraped-123')
        ->and($listing->original_url)->toBe('https://example.com/listing/123')
        ->and($listing->operations)->toBeArray()
        ->and($listing->external_codes)->toHaveKey('easybroker')
        ->and($listing->scraped_at)->not->toBeNull();
});

it('uses discovered listing external_id when not in scraped data', function () {
    $platform = Platform::factory()->create();
    $discoveredListing = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'external_id' => 'original-ext-id',
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('scrapeListing')
        ->andReturn([
            'operations' => [],
        ]);

    $job = new ScrapeListingJob($discoveredListing->id);
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    $listing = Listing::first();
    expect($listing->external_id)->toBe('original-ext-id');
});

it('increments attempts on successful scrape', function () {
    $platform = Platform::factory()->create();
    $discoveredListing = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'attempts' => 2,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('scrapeListing')
        ->andReturn(['operations' => []]);

    $job = new ScrapeListingJob($discoveredListing->id);
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    $discoveredListing->refresh();
    expect($discoveredListing->attempts)->toBe(3)
        ->and($discoveredListing->last_attempt_at)->not->toBeNull();
});

it('marks discovered listing as failed on exception', function () {
    $platform = Platform::factory()->create();
    $discoveredListing = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'attempts' => 0,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('scrapeListing')
        ->andThrow(new \RuntimeException('Scrape failed'));

    $job = new ScrapeListingJob($discoveredListing->id);

    try {
        $job->handle($mockService, app(ScrapeOrchestrator::class));
    } catch (\RuntimeException) {
        // Expected
    }

    $discoveredListing->refresh();
    expect($discoveredListing->status)->toBe(DiscoveredListingStatus::Failed)
        ->and($discoveredListing->attempts)->toBe(1);
});

it('marks scrape job as failed on exception', function () {
    $platform = Platform::factory()->create();
    $discoveredListing = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('scrapeListing')
        ->andThrow(new \RuntimeException('Connection timeout'));

    $job = new ScrapeListingJob($discoveredListing->id);

    try {
        $job->handle($mockService, app(ScrapeOrchestrator::class));
    } catch (\RuntimeException) {
        // Expected
    }

    $scrapeJob = ScrapeJob::first();
    expect($scrapeJob->status)->toBe(ScrapeJobStatus::Failed)
        ->and($scrapeJob->error_message)->toBe('Connection timeout');
});

it('stores listing id in scrape job result', function () {
    $platform = Platform::factory()->create();
    $discoveredListing = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('scrapeListing')
        ->andReturn([
            'external_id' => 'abc123',
            'operations' => [],
        ]);

    $job = new ScrapeListingJob($discoveredListing->id);
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    $scrapeJob = ScrapeJob::first();
    $listing = Listing::first();

    expect($scrapeJob->result)->toBeArray()
        ->and($scrapeJob->result['listing_id'])->toBe($listing->id)
        ->and($scrapeJob->result['external_id'])->toBe('abc123');
});
