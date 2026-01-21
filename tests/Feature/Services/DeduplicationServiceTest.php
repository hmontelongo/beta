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

it('marks unique listings with no matches for direct property creation', function () {
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

    // Unique listings get marked for direct property creation (no group)
    expect($listing->dedup_status)->toBe(DedupStatus::Unique)
        ->and($listing->listing_group_id)->toBeNull()
        ->and($listing->dedup_checked_at)->not->toBeNull();
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
        ->and((float) $group->match_score)->toBe(0.92)
        ->and($group->listings()->count())->toBe(2);
});

it('creates a review group with both listings for uncertain matches', function () {
    // When a listing has a needs_review match with another listing that has no group,
    // both listings are placed in the same group for human review
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
    $existingListing->refresh();

    // Both listings should be in the same review group
    expect($newListing->listing_group_id)->toBe($existingListing->listing_group_id);

    $group = ListingGroup::find($newListing->listing_group_id);
    expect($group->status)->toBe(ListingGroupStatus::PendingReview)
        ->and((float) $group->match_score)->toBe(0.75)
        ->and($group->listings()->count())->toBe(2);

    // Existing listing is primary, new listing is not
    expect($existingListing->is_primary_in_group)->toBeTrue()
        ->and($newListing->is_primary_in_group)->toBeFalse();
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

it('creates review group with matched_property_id when matching completed group', function () {
    // Create a completed group with a property
    $property = Property::factory()->create();
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

    // Create a new listing that might match the existing one
    $newListing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Processing,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    // Create an uncertain match candidate (needs review)
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

    // New listing should be in a NEW group (not the completed one)
    expect($newListing->listing_group_id)->not->toBe($completedGroup->id)
        ->and($newListing->dedup_status)->toBe(DedupStatus::Grouped);

    // The new group should reference the matched property
    $newGroup = ListingGroup::find($newListing->listing_group_id);
    expect($newGroup->status)->toBe(ListingGroupStatus::PendingReview)
        ->and($newGroup->matched_property_id)->toBe($property->id)
        ->and((float) $newGroup->match_score)->toBe(0.75)
        ->and($newGroup->listings()->count())->toBe(1);

    // The completed group should remain untouched
    $completedGroup->refresh();
    expect($completedGroup->status)->toBe(ListingGroupStatus::Completed)
        ->and($completedGroup->property_id)->toBe($property->id);
});

it('rejects group with matched_property_id and marks all property listings as different', function () {
    // Create a property with multiple listings
    $property = Property::factory()->create();
    $completedGroup = ListingGroup::factory()->completed()->create([
        'property_id' => $property->id,
    ]);

    $propertyListing1 = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'property_id' => $property->id,
        'dedup_status' => DedupStatus::Completed,
        'listing_group_id' => $completedGroup->id,
        'is_primary_in_group' => true,
    ]);
    $propertyListing2 = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'property_id' => $property->id,
        'dedup_status' => DedupStatus::Completed,
        'listing_group_id' => $completedGroup->id,
        'is_primary_in_group' => false,
    ]);

    // Create a review group with a listing that's being matched against the property
    $reviewGroup = ListingGroup::factory()->pendingReview()->create([
        'match_score' => 0.75,
        'matched_property_id' => $property->id,
    ]);

    $newListing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $reviewGroup->id,
        'is_primary_in_group' => true,
    ]);

    // Create candidates between the new listing and both property listings
    DedupCandidate::create([
        'listing_a_id' => $propertyListing1->id,
        'listing_b_id' => $newListing->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.75,
    ]);
    DedupCandidate::create([
        'listing_a_id' => $propertyListing2->id,
        'listing_b_id' => $newListing->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.73,
    ]);

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $service = new DeduplicationService($mockMatcher);
    $service->rejectGroup($reviewGroup, 'Not the same property');

    // Both candidates should be marked as confirmed different
    $candidate1 = DedupCandidate::where('listing_a_id', $propertyListing1->id)
        ->where('listing_b_id', $newListing->id)
        ->first();
    $candidate2 = DedupCandidate::where('listing_a_id', $propertyListing2->id)
        ->where('listing_b_id', $newListing->id)
        ->first();

    expect($candidate1->status)->toBe(DedupCandidateStatus::ConfirmedDifferent)
        ->and($candidate2->status)->toBe(DedupCandidateStatus::ConfirmedDifferent);

    // The new listing should be reset to pending
    $newListing->refresh();
    expect($newListing->dedup_status)->toBe(DedupStatus::Pending)
        ->and($newListing->listing_group_id)->toBeNull();
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
    Listing::factory()->unmatched()->count(5)->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Unique,
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
        ->and($stats['unique'])->toBe(5)
        ->and($stats['completed'])->toBe(4)
        ->and($stats['groups_pending_review'])->toBe(2)
        ->and($stats['groups_pending_ai'])->toBe(3);
});

it('creates PendingAi group when high confidence match against listing with property directly', function () {
    // Create a listing that has a property directly (no group) - simulating unique listing flow
    $property = Property::factory()->create();
    $existingListing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'property_id' => $property->id,
        'listing_group_id' => null,  // No group - property was created directly
        'dedup_status' => DedupStatus::Completed,
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

    // High confidence match (ConfirmedMatch status, score >= 0.92)
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

    // New listing should be grouped
    expect($newListing->dedup_status)->toBe(DedupStatus::Grouped)
        ->and($newListing->listing_group_id)->not->toBeNull();

    // The group should be PendingAi because ConfirmedMatch candidates always go to AI
    $group = ListingGroup::find($newListing->listing_group_id);
    expect($group->status)->toBe(ListingGroupStatus::PendingAi)
        ->and($group->matched_property_id)->toBe($property->id)
        ->and((float) $group->match_score)->toBe(0.95)
        ->and($group->listings()->count())->toBe(1);

    // Property should be marked for reanalysis
    $property->refresh();
    expect($property->needs_reanalysis)->toBeTrue();
});

it('marks listing as waiting when it does not match all group members', function () {
    // Create a group with two listings (A and B)
    $existingGroup = ListingGroup::factory()->pendingReview()->create(['match_score' => 0.80]);

    $listingA = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $existingGroup->id,
        'is_primary_in_group' => true,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    $listingB = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $existingGroup->id,
        'is_primary_in_group' => false,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    // Create a new listing C that matches A but NOT B
    $listingC = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Processing,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    // Candidate between A and B (already in group)
    DedupCandidate::create([
        'listing_a_id' => $listingA->id,
        'listing_b_id' => $listingB->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.80,
    ]);

    // Candidate C-A: good match (above threshold)
    DedupCandidate::create([
        'listing_a_id' => $listingA->id,
        'listing_b_id' => $listingC->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.78,
    ]);

    // Candidate C-B: confirmed different (below threshold)
    DedupCandidate::create([
        'listing_a_id' => $listingB->id,
        'listing_b_id' => $listingC->id,
        'status' => DedupCandidateStatus::ConfirmedDifferent,
        'overall_score' => 0.50,
    ]);

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $mockMatcher->shouldReceive('findCandidates')
        ->once()
        ->andReturn(collect([
            DedupCandidate::where('listing_a_id', $listingA->id)
                ->where('listing_b_id', $listingC->id)
                ->first(),
        ]));

    $service = new DeduplicationService($mockMatcher);
    $service->processListing($listingC);

    $listingC->refresh();

    // Listing C should be waiting (not added to group)
    expect($listingC->dedup_status)->toBe(DedupStatus::Waiting)
        ->and($listingC->listing_group_id)->toBeNull()
        ->and($listingC->waiting_for_group_id)->toBe($existingGroup->id);

    // Group should still have only 2 listings
    expect($existingGroup->listings()->count())->toBe(2);
});

it('adds listing to group when it matches all existing members', function () {
    // Create a group with two listings (A and B)
    $existingGroup = ListingGroup::factory()->pendingReview()->create(['match_score' => 0.80]);

    $listingA = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $existingGroup->id,
        'is_primary_in_group' => true,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    $listingB = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $existingGroup->id,
        'is_primary_in_group' => false,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    // Create a new listing C that matches BOTH A and B
    $listingC = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Processing,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    // Candidate between A and B (already in group)
    DedupCandidate::create([
        'listing_a_id' => $listingA->id,
        'listing_b_id' => $listingB->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.80,
    ]);

    // Candidate C-A: good match
    DedupCandidate::create([
        'listing_a_id' => $listingA->id,
        'listing_b_id' => $listingC->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.78,
    ]);

    // Candidate C-B: also good match
    DedupCandidate::create([
        'listing_a_id' => $listingB->id,
        'listing_b_id' => $listingC->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.76,
    ]);

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $mockMatcher->shouldReceive('findCandidates')
        ->once()
        ->andReturn(collect([
            DedupCandidate::where('listing_a_id', $listingA->id)
                ->where('listing_b_id', $listingC->id)
                ->first(),
        ]));

    $service = new DeduplicationService($mockMatcher);
    $service->processListing($listingC);

    $listingC->refresh();
    $existingGroup->refresh();

    // Listing C should be added to the group
    expect($listingC->dedup_status)->toBe(DedupStatus::Grouped)
        ->and($listingC->listing_group_id)->toBe($existingGroup->id)
        ->and($listingC->waiting_for_group_id)->toBeNull();

    // Group should now have 3 listings
    expect($existingGroup->listings()->count())->toBe(3);
});

it('marks listing as waiting when no candidate exists for a group member', function () {
    // Create a group with two listings (A and B)
    $existingGroup = ListingGroup::factory()->pendingReview()->create(['match_score' => 0.80]);

    $listingA = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $existingGroup->id,
        'is_primary_in_group' => true,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    $listingB = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Grouped,
        'listing_group_id' => $existingGroup->id,
        'is_primary_in_group' => false,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    // Create a new listing C
    $listingC = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'dedup_status' => DedupStatus::Processing,
        'geocode_status' => 'success',
        'latitude' => 20.65,
        'longitude' => -103.35,
    ]);

    // Candidate between A and B (already in group)
    DedupCandidate::create([
        'listing_a_id' => $listingA->id,
        'listing_b_id' => $listingB->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.80,
    ]);

    // Only candidate C-A exists (C-B was never compared)
    $candidateCA = DedupCandidate::create([
        'listing_a_id' => $listingA->id,
        'listing_b_id' => $listingC->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.78,
    ]);

    // NO candidate between C and B - never compared

    $mockMatcher = Mockery::mock(CandidateMatcherService::class);
    $mockMatcher->shouldReceive('findCandidates')
        ->once()
        ->andReturn(collect([$candidateCA]));

    $service = new DeduplicationService($mockMatcher);
    $service->processListing($listingC);

    $listingC->refresh();

    // Listing C should be waiting (no candidate with B)
    expect($listingC->dedup_status)->toBe(DedupStatus::Waiting)
        ->and($listingC->listing_group_id)->toBeNull()
        ->and($listingC->waiting_for_group_id)->toBe($existingGroup->id);

    // Group should still have only 2 listings
    expect($existingGroup->listings()->count())->toBe(2);
});

it('marks property for reanalysis when NeedsReview candidate matches listing with property', function () {
    // Create a listing that has a property directly (no group) - simulating unique listing flow
    $property = Property::factory()->create(['needs_reanalysis' => false]);
    $existingListing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'property_id' => $property->id,
        'listing_group_id' => null,
        'dedup_status' => DedupStatus::Completed,
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

    // Uncertain match (NeedsReview status, score between 0.65 and 0.92)
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

    // New listing should be in a review group
    expect($newListing->dedup_status)->toBe(DedupStatus::Grouped)
        ->and($newListing->listing_group_id)->not->toBeNull();

    // The group should be PendingReview with matched_property_id
    $group = ListingGroup::find($newListing->listing_group_id);
    expect($group->status)->toBe(ListingGroupStatus::PendingReview)
        ->and($group->matched_property_id)->toBe($property->id)
        ->and($group->listings()->count())->toBe(1);

    // Property should be marked for reanalysis even though it's a review candidate
    $property->refresh();
    expect($property->needs_reanalysis)->toBeTrue();
});
