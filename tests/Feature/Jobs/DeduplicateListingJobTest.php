<?php

use App\Enums\AiEnrichmentStatus;
use App\Enums\DedupStatus;
use App\Jobs\DeduplicateListingJob;
use App\Models\Listing;
use App\Models\Platform;
use App\Services\Dedup\DeduplicationService;

it('sets status to processing before calling service', function () {
    $platform = Platform::factory()->create();
    $listing = Listing::factory()->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Completed,
        'dedup_status' => DedupStatus::Pending,
        'raw_data' => ['title' => 'Test listing', 'description' => 'Test description'],
    ]);

    $statusDuringProcess = null;

    $mockService = Mockery::mock(DeduplicationService::class);
    $mockService->shouldReceive('processListing')
        ->once()
        ->andReturnUsing(function (Listing $l) use (&$statusDuringProcess) {
            // Capture the status during processing
            $statusDuringProcess = $l->fresh()->dedup_status;
        });

    $job = new DeduplicateListingJob($listing->id);
    $job->handle($mockService);

    expect($statusDuringProcess)->toBe(DedupStatus::Processing);
});

it('calls deduplication service with the listing', function () {
    $platform = Platform::factory()->create();
    $listing = Listing::factory()->create([
        'platform_id' => $platform->id,
        'raw_data' => ['title' => 'Test listing'],
    ]);

    $mockService = Mockery::mock(DeduplicationService::class);
    $mockService->shouldReceive('processListing')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->id === $listing->id));

    $job = new DeduplicateListingJob($listing->id);
    $job->handle($mockService);
});

it('skips processing when listing not found', function () {
    $mockService = Mockery::mock(DeduplicationService::class);
    $mockService->shouldNotReceive('processListing');

    $job = new DeduplicateListingJob(99999);
    $job->handle($mockService);

    // Should complete without error
    expect(true)->toBeTrue();
});

it('skips processing when listing has empty raw data', function () {
    $listing = Listing::factory()->create([
        'raw_data' => [], // Empty array is falsy in PHP
    ]);

    $mockService = Mockery::mock(DeduplicationService::class);
    $mockService->shouldNotReceive('processListing');

    $job = new DeduplicateListingJob($listing->id);
    $job->handle($mockService);

    $listing->refresh();
    // Status should not change to Processing since we skipped
    expect($listing->dedup_status)->not->toBe(DedupStatus::Processing);
});

it('sets status to failed when job fails permanently', function () {
    $listing = Listing::factory()->create([
        'dedup_status' => DedupStatus::Processing,
    ]);

    $job = new DeduplicateListingJob($listing->id);
    $job->failed(new \RuntimeException('Processing Error'));

    $listing->refresh();
    expect($listing->dedup_status)->toBe(DedupStatus::Failed);
});

it('is queued on the dedup queue', function () {
    $job = new DeduplicateListingJob(1);

    expect($job->queue)->toBe('dedup');
});

it('has retry configuration', function () {
    $job = new DeduplicateListingJob(1);

    expect($job->backoff())->toBe([15, 30])
        ->and($job->tries)->toBe(2)
        ->and($job->timeout)->toBe(60);
});
