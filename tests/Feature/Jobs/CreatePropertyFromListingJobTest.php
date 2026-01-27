<?php

use App\Enums\DedupStatus;
use App\Jobs\CreatePropertyFromListingJob;
use App\Models\Listing;
use App\Models\Platform;
use App\Models\Property;
use App\Services\AI\PropertyCreationService;

it('processes unique listing through the service', function () {
    $platform = Platform::factory()->create();
    $listing = Listing::factory()->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Unique,
        'property_id' => null,
        'raw_data' => ['title' => 'Test', 'description' => 'Test description'],
    ]);

    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldReceive('createPropertyFromListing')
        ->once()
        ->with(Mockery::on(fn ($l) => $l->id === $listing->id))
        ->andReturn(Property::factory()->create());

    $job = new CreatePropertyFromListingJob($listing->id);
    $job->handle($mockService);
});

it('skips processing when listing not found', function () {
    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldNotReceive('createPropertyFromListing');

    $job = new CreatePropertyFromListingJob(99999);
    $job->handle($mockService);

    // Should complete without error
    expect(true)->toBeTrue();
});

it('skips processing when listing is not in unique status', function () {
    $platform = Platform::factory()->create();
    $listing = Listing::factory()->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Pending,
        'property_id' => null,
    ]);

    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldNotReceive('createPropertyFromListing');

    $job = new CreatePropertyFromListingJob($listing->id);
    $job->handle($mockService);

    $listing->refresh();
    expect($listing->dedup_status)->toBe(DedupStatus::Pending);
});

it('skips processing when listing is grouped', function () {
    $platform = Platform::factory()->create();
    $listing = Listing::factory()->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'property_id' => null,
    ]);

    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldNotReceive('createPropertyFromListing');

    $job = new CreatePropertyFromListingJob($listing->id);
    $job->handle($mockService);

    $listing->refresh();
    expect($listing->dedup_status)->toBe(DedupStatus::Grouped);
});

it('skips processing when listing already has property and marks as completed', function () {
    $platform = Platform::factory()->create();
    $property = Property::factory()->create();
    $listing = Listing::factory()->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Unique,
        'property_id' => $property->id,
    ]);

    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldNotReceive('createPropertyFromListing');

    $job = new CreatePropertyFromListingJob($listing->id);
    $job->handle($mockService);

    $listing->refresh();
    expect($listing->dedup_status)->toBe(DedupStatus::Completed);
});

it('sets listing to failed when job fails permanently', function () {
    $platform = Platform::factory()->create();
    $listing = Listing::factory()->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Unique,
        'property_id' => null,
    ]);

    $job = new CreatePropertyFromListingJob($listing->id);
    $job->failed(new \RuntimeException('API Error'));

    $listing->refresh();
    expect($listing->dedup_status)->toBe(DedupStatus::Failed);
});

it('is queued on the property-creation queue', function () {
    $job = new CreatePropertyFromListingJob(1);

    expect($job->queue)->toBe('property-creation');
});

it('has timeout and retry configuration', function () {
    $job = new CreatePropertyFromListingJob(1);

    expect($job->timeout)->toBe(180)
        ->and($job->maxExceptions)->toBe(5)
        ->and($job->retryUntil())->toBeInstanceOf(\DateTime::class);
});

it('has unique job constraint by listing id', function () {
    $job1 = new CreatePropertyFromListingJob(123);
    $job2 = new CreatePropertyFromListingJob(456);

    expect($job1->uniqueId())->toBe('create-property-listing-123')
        ->and($job2->uniqueId())->toBe('create-property-listing-456')
        ->and($job1->uniqueFor)->toBe(300);
});
