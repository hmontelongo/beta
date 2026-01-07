<?php

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Jobs\DiscoverPageJob;
use App\Jobs\DiscoverSearchJob;
use App\Models\DiscoveredListing;
use App\Models\Platform;
use App\Models\ScrapeJob;
use App\Services\ScrapeOrchestrator;
use App\Services\ScraperService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('creates a scrape job when dispatched', function () {
    $platform = Platform::factory()->create();

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->with('https://example.com/search', 1)
        ->once()
        ->andReturn([
            'total_results' => 20,
            'total_pages' => 1,
            'listings' => [
                ['url' => 'https://example.com/listing/1', 'external_id' => 'abc123'],
            ],
        ]);

    app()->instance(ScraperService::class, $mockService);

    $job = new DiscoverSearchJob($platform->id, 'https://example.com/search');
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    expect(ScrapeJob::count())->toBe(1);

    $scrapeJob = ScrapeJob::first();
    expect($scrapeJob->platform_id)->toBe($platform->id)
        ->and($scrapeJob->target_url)->toBe('https://example.com/search')
        ->and($scrapeJob->job_type)->toBe(ScrapeJobType::Discovery)
        ->and($scrapeJob->status)->toBe(ScrapeJobStatus::Completed)
        ->and($scrapeJob->total_results)->toBe(20)
        ->and($scrapeJob->total_pages)->toBe(1);
});

it('stores discovered listings', function () {
    $platform = Platform::factory()->create();

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andReturn([
            'total_results' => 40,
            'total_pages' => 2,
            'listings' => [
                ['url' => 'https://example.com/listing/1', 'external_id' => 'abc123'],
                ['url' => 'https://example.com/listing/2', 'external_id' => 'def456'],
            ],
        ]);

    $job = new DiscoverSearchJob($platform->id, 'https://example.com/search');
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    expect(DiscoveredListing::count())->toBe(2);

    $listing = DiscoveredListing::where('url', 'https://example.com/listing/1')->first();
    expect($listing->platform_id)->toBe($platform->id)
        ->and($listing->external_id)->toBe('abc123')
        ->and($listing->status)->toBe(DiscoveredListingStatus::Pending);
});

it('dispatches page jobs for additional pages', function () {
    $platform = Platform::factory()->create();

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andReturn([
            'total_results' => 100,
            'total_pages' => 5,
            'listings' => [
                ['url' => 'https://example.com/listing/1', 'external_id' => 'abc123'],
            ],
        ]);

    $job = new DiscoverSearchJob($platform->id, 'https://example.com/search');
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    Queue::assertPushed(DiscoverPageJob::class, 4);

    Queue::assertPushed(DiscoverPageJob::class, function ($job) {
        return $job->pageNumber === 2;
    });

    Queue::assertPushed(DiscoverPageJob::class, function ($job) {
        return $job->pageNumber === 5;
    });
});

it('does not dispatch page jobs for single page results', function () {
    $platform = Platform::factory()->create();

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andReturn([
            'total_results' => 10,
            'total_pages' => 1,
            'listings' => [
                ['url' => 'https://example.com/listing/1', 'external_id' => 'abc123'],
            ],
        ]);

    $job = new DiscoverSearchJob($platform->id, 'https://example.com/search');
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    Queue::assertNotPushed(DiscoverPageJob::class);
});

it('marks job as failed on exception', function () {
    $platform = Platform::factory()->create();

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andThrow(new \RuntimeException('Connection failed'));

    $job = new DiscoverSearchJob($platform->id, 'https://example.com/search');

    try {
        $job->handle($mockService, app(ScrapeOrchestrator::class));
    } catch (\RuntimeException) {
        // Expected
    }

    $scrapeJob = ScrapeJob::first();
    expect($scrapeJob->status)->toBe(ScrapeJobStatus::Failed)
        ->and($scrapeJob->error_message)->toBe('Connection failed');
});

it('skips duplicates when storing listings', function () {
    $platform = Platform::factory()->create();

    DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'url' => 'https://example.com/listing/1',
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andReturn([
            'total_results' => 20,
            'total_pages' => 1,
            'listings' => [
                ['url' => 'https://example.com/listing/1', 'external_id' => 'abc123'],
                ['url' => 'https://example.com/listing/2', 'external_id' => 'def456'],
            ],
        ]);

    $job = new DiscoverSearchJob($platform->id, 'https://example.com/search');
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    expect(DiscoveredListing::count())->toBe(2);
});

it('stores batch id with discovered listings', function () {
    $platform = Platform::factory()->create();

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andReturn([
            'total_results' => 20,
            'total_pages' => 1,
            'listings' => [
                ['url' => 'https://example.com/listing/1', 'external_id' => 'abc123'],
            ],
        ]);

    $job = new DiscoverSearchJob($platform->id, 'https://example.com/search');
    $job->handle($mockService, app(ScrapeOrchestrator::class));

    $scrapeJob = ScrapeJob::first();
    $listing = DiscoveredListing::first();

    expect($listing->batch_id)->toBe((string) $scrapeJob->id);
});
