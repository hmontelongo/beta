<?php

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Jobs\DiscoverPageJob;
use App\Models\DiscoveredListing;
use App\Models\Platform;
use App\Models\ScrapeJob;
use App\Services\ScraperService;

it('creates a child scrape job', function () {
    $platform = Platform::factory()->create();
    $parentJob = ScrapeJob::factory()->create([
        'platform_id' => $platform->id,
        'job_type' => ScrapeJobType::Discovery,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->with('https://example.com/search', 2)
        ->once()
        ->andReturn([
            'total_results' => 100,
            'total_pages' => 5,
            'listings' => [
                ['url' => 'https://example.com/listing/21', 'external_id' => 'abc123'],
            ],
        ]);

    $job = new DiscoverPageJob($parentJob->id, 'https://example.com/search', 2);
    $job->handle($mockService);

    expect(ScrapeJob::count())->toBe(2);

    $childJob = ScrapeJob::where('parent_id', $parentJob->id)->first();
    expect($childJob->platform_id)->toBe($platform->id)
        ->and($childJob->parent_id)->toBe($parentJob->id)
        ->and($childJob->current_page)->toBe(2)
        ->and($childJob->job_type)->toBe(ScrapeJobType::Discovery)
        ->and($childJob->status)->toBe(ScrapeJobStatus::Completed);
});

it('stores discovered listings with parent batch id', function () {
    $platform = Platform::factory()->create();
    $parentJob = ScrapeJob::factory()->create([
        'platform_id' => $platform->id,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andReturn([
            'total_results' => 100,
            'total_pages' => 5,
            'listings' => [
                ['url' => 'https://example.com/listing/21', 'external_id' => 'abc123'],
                ['url' => 'https://example.com/listing/22', 'external_id' => 'def456'],
            ],
        ]);

    $job = new DiscoverPageJob($parentJob->id, 'https://example.com/search', 2);
    $job->handle($mockService);

    expect(DiscoveredListing::count())->toBe(2);

    $listing = DiscoveredListing::first();
    expect($listing->batch_id)->toBe((string) $parentJob->id)
        ->and($listing->platform_id)->toBe($platform->id)
        ->and($listing->status)->toBe(DiscoveredListingStatus::Pending);
});

it('marks job as failed on exception', function () {
    $platform = Platform::factory()->create();
    $parentJob = ScrapeJob::factory()->create([
        'platform_id' => $platform->id,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andThrow(new \RuntimeException('Page fetch failed'));

    $job = new DiscoverPageJob($parentJob->id, 'https://example.com/search', 3);

    try {
        $job->handle($mockService);
    } catch (\RuntimeException) {
        // Expected
    }

    $childJob = ScrapeJob::where('parent_id', $parentJob->id)->first();
    expect($childJob->status)->toBe(ScrapeJobStatus::Failed)
        ->and($childJob->error_message)->toBe('Page fetch failed');
});

it('skips duplicate urls', function () {
    $platform = Platform::factory()->create();
    $parentJob = ScrapeJob::factory()->create([
        'platform_id' => $platform->id,
    ]);

    DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'url' => 'https://example.com/listing/21',
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andReturn([
            'total_results' => 100,
            'total_pages' => 5,
            'listings' => [
                ['url' => 'https://example.com/listing/21', 'external_id' => 'abc123'],
                ['url' => 'https://example.com/listing/22', 'external_id' => 'def456'],
            ],
        ]);

    $job = new DiscoverPageJob($parentJob->id, 'https://example.com/search', 2);
    $job->handle($mockService);

    expect(DiscoveredListing::count())->toBe(2);
});

it('stores result with listings count', function () {
    $platform = Platform::factory()->create();
    $parentJob = ScrapeJob::factory()->create([
        'platform_id' => $platform->id,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andReturn([
            'total_results' => 100,
            'total_pages' => 5,
            'listings' => [
                ['url' => 'https://example.com/listing/21', 'external_id' => 'abc123'],
                ['url' => 'https://example.com/listing/22', 'external_id' => 'def456'],
                ['url' => 'https://example.com/listing/23', 'external_id' => 'ghi789'],
            ],
        ]);

    $job = new DiscoverPageJob($parentJob->id, 'https://example.com/search', 2);
    $job->handle($mockService);

    $childJob = ScrapeJob::where('parent_id', $parentJob->id)->first();
    expect($childJob->result)->toBeArray()
        ->and($childJob->result['listings_found'])->toBe(3);
});

it('handles nullable external_id', function () {
    $platform = Platform::factory()->create();
    $parentJob = ScrapeJob::factory()->create([
        'platform_id' => $platform->id,
    ]);

    $mockService = Mockery::mock(ScraperService::class);
    $mockService->shouldReceive('discoverPage')
        ->andReturn([
            'total_results' => 100,
            'total_pages' => 5,
            'listings' => [
                ['url' => 'https://example.com/listing/21', 'external_id' => null],
            ],
        ]);

    $job = new DiscoverPageJob($parentJob->id, 'https://example.com/search', 2);
    $job->handle($mockService);

    $listing = DiscoveredListing::first();
    expect($listing->external_id)->toBeNull();
});
