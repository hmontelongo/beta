<?php

use App\Enums\DedupCandidateStatus;
use App\Enums\DedupStatus;
use App\Enums\ListingGroupStatus;
use App\Models\DedupCandidate;
use App\Models\Listing;
use App\Models\ListingGroup;
use App\Models\Platform;
use App\Models\Property;
use App\Services\Dedup\CandidateMatcherService;
use App\Services\Dedup\DeduplicationService;

beforeEach(function () {
    $this->platform = Platform::factory()->create();
});

it('creates a single listing group for unique listings with no matches', function () {
    $listing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Processing,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
        'raw_data' => [
            'title' => 'Test listing',
            'description' => 'Test description',
        ],
    ]);

    // Mock matcher to return no candidates
    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $mockMatcher->shouldReceive('findCandidates')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->id === $listing->id))
        ->andReturn(collect());

    $service = new DeduplicationService($mockMatcher);
    $service->processListing($listing);

    $listing->refresh();

    expect($listing->dedup_status)->toBe(DedupStatus::Grouped)
        ->and($listing->listing_group_id)->not->toBeNull();

    $group = ListingGroup::find($listing->listing_group_id);
    expect($group->status)->toBe(ListingGroupStatus::PendingAi)
        ->and($group->match_score)->toBeNull()
        ->and($group->listings()->count())->toBe(1);
});

it('creates a match group for high confidence matches', function () {
    $existingListing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Pending,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
        'raw_data' => [
            'title' => 'Existing listing',
        ],
    ]);

    $newListing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Processing,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
        'raw_data' => [
            'title' => 'New listing',
        ],
    ]);

    // Create a high confidence candidate
    $candidate = DedupCandidate::create([
        'listing_a_id' => $existingListing->id,
        'listing_b_id' => $newListing->id,
        'status' => DedupCandidateStatus::ConfirmedMatch,
        'overall_score' => 0.92,
        'coordinate_score' => 1.0,
        'address_score' => 0.9,
        'features_score' => 0.95,
    ]);

    // Mock matcher to return the candidate
    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $mockMatcher->shouldReceive('findCandidates')
        ->once()
        ->andReturn(collect([$candidate]));

    $service = new DeduplicationService($mockMatcher);
    $service->processListing($newListing);

    $newListing->refresh();
    $existingListing->refresh();

    // Both listings should be in the same group
    expect($newListing->dedup_status)->toBe(DedupStatus::Grouped)
        ->and($existingListing->dedup_status)->toBe(DedupStatus::Grouped)
        ->and($newListing->listing_group_id)->toBe($existingListing->listing_group_id);

    $group = ListingGroup::find($newListing->listing_group_id);
    expect($group->status)->toBe(ListingGroupStatus::PendingAi)
        ->and($group->match_score)->toBe('0.92')
        ->and($group->listings()->count())->toBe(2);
});

it('creates a review group for uncertain matches', function () {
    $existingListing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Pending,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
        'raw_data' => ['title' => 'Existing'],
    ]);

    $newListing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Processing,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
        'raw_data' => ['title' => 'New'],
    ]);

    // Create an uncertain candidate
    $candidate = DedupCandidate::create([
        'listing_a_id' => $existingListing->id,
        'listing_b_id' => $newListing->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.75,
        'coordinate_score' => 0.8,
        'address_score' => 0.7,
        'features_score' => 0.75,
    ]);

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $mockMatcher->shouldReceive('findCandidates')
        ->once()
        ->andReturn(collect([$candidate]));

    $service = new DeduplicationService($mockMatcher);
    $service->processListing($newListing);

    $newListing->refresh();

    $group = ListingGroup::find($newListing->listing_group_id);
    expect($group->status)->toBe(ListingGroupStatus::PendingReview)
        ->and($group->match_score)->toBe('0.75')
        ->and($group->listings()->count())->toBe(2);
});

it('skips processing for listings already linked to a property', function () {
    $property = Property::factory()->create();
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'property_id' => $property->id,
        'dedup_status' => DedupStatus::Processing,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $mockMatcher->shouldNotReceive('findCandidates');

    $service = new DeduplicationService($mockMatcher);
    $service->processListing($listing);

    $listing->refresh();
    expect($listing->dedup_status)->toBe(DedupStatus::Completed);
});

it('adds new listing to existing group when matched listing has a group', function () {
    // Create existing listing with a group
    $existingGroup = ListingGroup::factory()->pendingAi()->create();
    $existingListing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $existingGroup->id,
        'is_primary_in_group' => true,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    // Create new listing
    $newListing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Processing,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    $candidate = DedupCandidate::create([
        'listing_a_id' => $existingListing->id,
        'listing_b_id' => $newListing->id,
        'status' => DedupCandidateStatus::ConfirmedMatch,
        'overall_score' => 0.95,
        'coordinate_score' => 1.0,
        'address_score' => 0.9,
        'features_score' => 0.95,
    ]);

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $mockMatcher->shouldReceive('findCandidates')
        ->once()
        ->andReturn(collect([$candidate]));

    $service = new DeduplicationService($mockMatcher);
    $service->processListing($newListing);

    $newListing->refresh();

    // New listing should join the existing group
    expect($newListing->listing_group_id)->toBe($existingGroup->id);

    $existingGroup->refresh();
    expect($existingGroup->listings()->count())->toBe(2);
});

it('marks property for reanalysis when new listing joins completed group', function () {
    $property = Property::factory()->create(['needs_reanalysis' => false]);
    $completedGroup = ListingGroup::factory()->completed()->create([
        'property_id' => $property->id,
    ]);

    $existingListing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'property_id' => $property->id,
        'dedup_status' => DedupStatus::Completed,
        'listing_group_id' => $completedGroup->id,
        'is_primary_in_group' => true,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    $newListing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Processing,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    $candidate = DedupCandidate::create([
        'listing_a_id' => $existingListing->id,
        'listing_b_id' => $newListing->id,
        'status' => DedupCandidateStatus::ConfirmedMatch,
        'overall_score' => 0.95,
    ]);

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $mockMatcher->shouldReceive('findCandidates')
        ->once()
        ->andReturn(collect([$candidate]));

    $service = new DeduplicationService($mockMatcher);
    $service->processListing($newListing);

    $property->refresh();
    $completedGroup->refresh();

    expect($property->needs_reanalysis)->toBeTrue()
        ->and($completedGroup->status)->toBe(ListingGroupStatus::PendingAi);
});

it('approves a pending review group', function () {
    $group = ListingGroup::factory()->pendingReview()->create(['match_score' => 0.75]);

    $listingA = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $group->id,
        'is_primary_in_group' => true,
    ]);
    $listingB = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $group->id,
        'is_primary_in_group' => false,
    ]);

    // Create candidate that needs review
    DedupCandidate::create([
        'listing_a_id' => $listingA->id,
        'listing_b_id' => $listingB->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.75,
    ]);

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $service = new DeduplicationService($mockMatcher);
    $service->approveGroup($group);

    $group->refresh();
    expect($group->status)->toBe(ListingGroupStatus::PendingAi);

    // Candidate should be confirmed
    $candidate = DedupCandidate::where('listing_a_id', $listingA->id)
        ->where('listing_b_id', $listingB->id)
        ->first();
    expect($candidate->status)->toBe(DedupCandidateStatus::ConfirmedMatch);
});

it('rejects a pending review group and resets listings', function () {
    $group = ListingGroup::factory()->pendingReview()->create(['match_score' => 0.75]);

    $listingA = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $group->id,
        'is_primary_in_group' => true,
    ]);
    $listingB = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $group->id,
        'is_primary_in_group' => false,
    ]);

    DedupCandidate::create([
        'listing_a_id' => $listingA->id,
        'listing_b_id' => $listingB->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.75,
    ]);

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $service = new DeduplicationService($mockMatcher);
    $service->rejectGroup($group, 'Not the same property');

    $group->refresh();
    $listingA->refresh();
    $listingB->refresh();

    expect($group->status)->toBe(ListingGroupStatus::Rejected)
        ->and($group->rejection_reason)->toBe('Not the same property');

    // Listings should be reset to pending with cleared group membership
    expect($listingA->dedup_status)->toBe(DedupStatus::Pending)
        ->and($listingA->listing_group_id)->toBeNull()
        ->and($listingA->is_primary_in_group)->toBeFalse()
        ->and($listingB->dedup_status)->toBe(DedupStatus::Pending)
        ->and($listingB->listing_group_id)->toBeNull()
        ->and($listingB->is_primary_in_group)->toBeFalse();

    // Candidate should be confirmed different
    $candidate = DedupCandidate::where('listing_a_id', $listingA->id)
        ->where('listing_b_id', $listingB->id)
        ->first();
    expect($candidate->status)->toBe(DedupCandidateStatus::ConfirmedDifferent);
});

it('does not approve a group not in pending review status', function () {
    $group = ListingGroup::factory()->pendingAi()->create();

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $service = new DeduplicationService($mockMatcher);
    $service->approveGroup($group);

    $group->refresh();
    expect($group->status)->toBe(ListingGroupStatus::PendingAi);
});

it('returns correct stats', function () {
    // Create listings in various states
    Listing::factory()->unmatched()->count(3)->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Pending,
    ]);
    Listing::factory()->unmatched()->count(2)->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
    ]);
    Listing::factory()->count(4)->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Completed,
    ]);

    // Create groups in various states
    ListingGroup::factory()->count(2)->pendingReview()->create();
    ListingGroup::factory()->count(3)->pendingAi()->create();

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $service = new DeduplicationService($mockMatcher);
    $stats = $service->getStats();

    expect($stats['pending'])->toBe(3)
        ->and($stats['grouped'])->toBe(2)
        ->and($stats['completed'])->toBe(4)
        ->and($stats['groups_pending_review'])->toBe(2)
        ->and($stats['groups_pending_ai'])->toBe(3);
});
