<?php

use App\Enums\AiEnrichmentStatus;
use App\Jobs\EnrichListingJob;
use App\Models\AiEnrichment;
use App\Models\Listing;
use App\Models\Platform;
use App\Services\AI\ListingEnrichmentService;

it('sets status to processing before calling service', function () {
    $platform = Platform::factory()->create();
    $listing = Listing::factory()->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Pending,
        'raw_data' => ['title' => 'Test listing', 'description' => 'Test description'],
    ]);

    $statusDuringProcess = null;

    $mockService = Mockery::mock(ListingEnrichmentService::class);
    $mockService->shouldReceive('enrichListing')
        ->once()
        ->andReturnUsing(function (Listing $l) use (&$statusDuringProcess, $listing) {
            // Capture the status during processing
            $statusDuringProcess = $l->fresh()->ai_status;

            // Return a mock enrichment
            return AiEnrichment::create([
                'listing_id' => $listing->id,
                'status' => AiEnrichmentStatus::Completed,
            ]);
        });

    $this->app->instance(ListingEnrichmentService::class, $mockService);

    $job = new EnrichListingJob($listing->id);
    $job->handle($mockService);

    expect($statusDuringProcess)->toBe(AiEnrichmentStatus::Processing);
});

it('calls enrichment service with the listing', function () {
    $platform = Platform::factory()->create();
    $listing = Listing::factory()->create([
        'platform_id' => $platform->id,
        'raw_data' => ['title' => 'Test listing'],
    ]);

    $mockService = Mockery::mock(ListingEnrichmentService::class);
    $mockService->shouldReceive('enrichListing')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->id === $listing->id))
        ->andReturn(AiEnrichment::create([
            'listing_id' => $listing->id,
            'status' => AiEnrichmentStatus::Completed,
        ]));

    $job = new EnrichListingJob($listing->id);
    $job->handle($mockService);
});

it('skips processing when listing not found', function () {
    $mockService = Mockery::mock(ListingEnrichmentService::class);
    $mockService->shouldNotReceive('enrichListing');

    $job = new EnrichListingJob(99999);
    $job->handle($mockService);

    // Should complete without error
    expect(true)->toBeTrue();
});

it('skips processing when listing has empty raw data', function () {
    $listing = Listing::factory()->create([
        'raw_data' => [], // Empty array is falsy in PHP
    ]);

    $mockService = Mockery::mock(ListingEnrichmentService::class);
    $mockService->shouldNotReceive('enrichListing');

    $job = new EnrichListingJob($listing->id);
    $job->handle($mockService);

    $listing->refresh();
    // Status should not change to Processing since we skipped
    expect($listing->ai_status)->not->toBe(AiEnrichmentStatus::Processing);
});

it('sets status to failed when job fails permanently', function () {
    $listing = Listing::factory()->create([
        'ai_status' => AiEnrichmentStatus::Processing,
    ]);

    $job = new EnrichListingJob($listing->id);
    $job->failed(new \RuntimeException('API Error'));

    $listing->refresh();
    expect($listing->ai_status)->toBe(AiEnrichmentStatus::Failed);
});

it('is queued on the ai-enrichment queue', function () {
    $job = new EnrichListingJob(1);

    expect($job->queue)->toBe('ai-enrichment');
});

it('has exponential backoff configured', function () {
    $job = new EnrichListingJob(1);

    expect($job->backoff())->toBe([60, 120, 180])
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(120);
});
