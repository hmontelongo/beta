<?php

use App\Enums\ListingGroupStatus;
use App\Jobs\CreatePropertyFromListingsJob;
use App\Models\Listing;
use App\Models\ListingGroup;
use App\Models\Platform;
use App\Models\Property;
use App\Services\AI\PropertyCreationService;

it('processes pending_ai group through the service', function () {
    $platform = Platform::factory()->create();
    $group = ListingGroup::factory()->pendingAi()->create();
    $listing = Listing::factory()->unmatched()->create([
        'platform_id' => $platform->id,
        'raw_data' => ['title' => 'Test', 'description' => 'Test description'],
        'listing_group_id' => $group->id,
        'is_primary_in_group' => true,
    ]);

    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldReceive('createPropertyFromGroup')
        ->once()
        ->with(Mockery::on(fn ($g) => $g->id === $group->id && $g->status === ListingGroupStatus::PendingAi))
        ->andReturn(Property::factory()->create());

    $job = new CreatePropertyFromListingsJob($group->id);
    $job->handle($mockService);
});

it('calls property creation service with the listing group', function () {
    $platform = Platform::factory()->create();
    $group = ListingGroup::factory()->pendingAi()->create();
    $listing = Listing::factory()->unmatched()->create([
        'platform_id' => $platform->id,
        'raw_data' => ['title' => 'Test', 'description' => 'Test description'],
        'listing_group_id' => $group->id,
        'is_primary_in_group' => true,
    ]);

    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldReceive('createPropertyFromGroup')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->id === $group->id))
        ->andReturn(Property::factory()->create());

    $job = new CreatePropertyFromListingsJob($group->id);
    $job->handle($mockService);
});

it('skips processing when listing group not found', function () {
    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldNotReceive('createPropertyFromGroup');

    $job = new CreatePropertyFromListingsJob(99999);
    $job->handle($mockService);

    // Should complete without error
    expect(true)->toBeTrue();
});

it('skips processing when group is already completed', function () {
    $platform = Platform::factory()->create();
    $property = Property::factory()->create();
    $group = ListingGroup::factory()->completed()->create([
        'property_id' => $property->id,
    ]);
    $listing = Listing::factory()->create([
        'platform_id' => $platform->id,
        'property_id' => $property->id,
        'listing_group_id' => $group->id,
        'is_primary_in_group' => true,
    ]);

    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldNotReceive('createPropertyFromGroup');

    $job = new CreatePropertyFromListingsJob($group->id);
    $job->handle($mockService);

    $group->refresh();
    expect($group->status)->toBe(ListingGroupStatus::Completed);
});

it('skips processing when group is rejected', function () {
    $group = ListingGroup::factory()->rejected()->create();

    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldNotReceive('createPropertyFromGroup');

    $job = new CreatePropertyFromListingsJob($group->id);
    $job->handle($mockService);

    $group->refresh();
    expect($group->status)->toBe(ListingGroupStatus::Rejected);
});

it('skips processing when group is still pending review', function () {
    $platform = Platform::factory()->create();
    $group = ListingGroup::factory()->pendingReview()->create();
    $listing = Listing::factory()->unmatched()->create([
        'platform_id' => $platform->id,
        'listing_group_id' => $group->id,
        'is_primary_in_group' => true,
    ]);

    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldNotReceive('createPropertyFromGroup');

    $job = new CreatePropertyFromListingsJob($group->id);
    $job->handle($mockService);

    $group->refresh();
    expect($group->status)->toBe(ListingGroupStatus::PendingReview);
});

it('sets group to failed with reason when job fails permanently', function () {
    $platform = Platform::factory()->create();
    $group = ListingGroup::factory()->processingAi()->create();
    $listing = Listing::factory()->unmatched()->create([
        'platform_id' => $platform->id,
        'listing_group_id' => $group->id,
        'is_primary_in_group' => true,
    ]);

    $job = new CreatePropertyFromListingsJob($group->id);
    $job->failed(new \RuntimeException('API Error'));

    $group->refresh();
    expect($group->status)->toBe(ListingGroupStatus::Failed)
        ->and($group->rejection_reason)->toContain('API Error');
});

it('is queued on the property-creation queue', function () {
    $job = new CreatePropertyFromListingsJob(1);

    expect($job->queue)->toBe('property-creation');
});

it('has timeout and retry configuration', function () {
    $job = new CreatePropertyFromListingsJob(1);

    expect($job->timeout)->toBe(180)
        ->and($job->maxExceptions)->toBe(5)
        ->and($job->retryUntil())->toBeInstanceOf(\DateTime::class);
});

it('deletes orphaned groups with no listings', function () {
    // Create a group with no listings (orphaned state from re-scraping)
    $group = ListingGroup::factory()->pendingAi()->create();
    $groupId = $group->id;

    // Ensure no listings are attached
    expect($group->listings()->count())->toBe(0);

    $mockService = Mockery::mock(PropertyCreationService::class);
    $mockService->shouldNotReceive('createPropertyFromGroup');

    $job = new CreatePropertyFromListingsJob($groupId);
    $job->handle($mockService);

    // Group should be deleted
    expect(ListingGroup::find($groupId))->toBeNull();
});
