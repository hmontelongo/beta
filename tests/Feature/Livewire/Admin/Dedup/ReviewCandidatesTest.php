<?php

use App\Enums\DedupStatus;
use App\Enums\ListingGroupStatus;
use App\Livewire\Admin\Dedup\ReviewCandidates;
use App\Models\Listing;
use App\Models\ListingGroup;
use App\Models\Platform;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->platform = Platform::factory()->create();
});

it('renders the review candidates page', function () {
    Livewire::test(ReviewCandidates::class)
        ->assertStatus(200);
});

it('shows empty state when no groups need review', function () {
    Livewire::test(ReviewCandidates::class)
        ->assertSee('All caught up!');
});

it('loads the first pending review group on mount', function () {
    $group = ListingGroup::factory()->pendingReview()->create();
    Listing::factory()->count(2)->create([
        'platform_id' => $this->platform->id,
        'listing_group_id' => $group->id,
        'dedup_status' => DedupStatus::Grouped,
    ]);

    Livewire::test(ReviewCandidates::class)
        ->assertSet('currentGroupId', $group->id);
});

it('can remove a listing from a group', function () {
    $group = ListingGroup::factory()->pendingReview()->create();
    $listings = Listing::factory()->count(3)->create([
        'platform_id' => $this->platform->id,
        'listing_group_id' => $group->id,
        'dedup_status' => DedupStatus::Grouped,
        'is_primary_in_group' => false,
    ]);
    $listings->first()->update(['is_primary_in_group' => true]);

    $listingToRemove = $listings->last();

    Livewire::test(ReviewCandidates::class)
        ->assertSet('currentGroupId', $group->id)
        ->call('removeListingFromGroup', $listingToRemove->id);

    // Verify listing was removed from group
    $listingToRemove->refresh();
    expect($listingToRemove->listing_group_id)->toBeNull()
        ->and($listingToRemove->dedup_status)->toBe(DedupStatus::Pending);

    // Verify group still has remaining listings
    $group->refresh();
    expect($group->listings()->count())->toBe(2);
});

it('dissolves group when only one listing remains after removal', function () {
    $group = ListingGroup::factory()->pendingReview()->create();
    $listings = Listing::factory()->count(2)->create([
        'platform_id' => $this->platform->id,
        'listing_group_id' => $group->id,
        'dedup_status' => DedupStatus::Grouped,
        'is_primary_in_group' => false,
    ]);
    $listings->first()->update(['is_primary_in_group' => true]);

    $listingToRemove = $listings->last();

    Livewire::test(ReviewCandidates::class)
        ->assertSet('currentGroupId', $group->id)
        ->call('removeListingFromGroup', $listingToRemove->id);

    // Verify the group was deleted
    expect(ListingGroup::find($group->id))->toBeNull();

    // Both listings should be reset to pending
    foreach ($listings as $listing) {
        $listing->refresh();
        expect($listing->listing_group_id)->toBeNull()
            ->and($listing->dedup_status)->toBe(DedupStatus::Pending);
    }
});

it('moves to next group after current group is resolved', function () {
    $group1 = ListingGroup::factory()->pendingReview()->highConfidence()->create();
    $group2 = ListingGroup::factory()->pendingReview()->lowConfidence()->create();

    Listing::factory()->count(2)->create([
        'platform_id' => $this->platform->id,
        'listing_group_id' => $group1->id,
        'dedup_status' => DedupStatus::Grouped,
    ]);
    Listing::factory()->count(2)->create([
        'platform_id' => $this->platform->id,
        'listing_group_id' => $group2->id,
        'dedup_status' => DedupStatus::Grouped,
    ]);

    // First group should be loaded (higher score)
    $component = Livewire::test(ReviewCandidates::class)
        ->assertSet('currentGroupId', $group1->id);

    // Approve the first group
    $component->call('approveGroup');

    // Should move to next group
    $component->assertSet('currentGroupId', $group2->id);
});

it('can select a different group from the queue', function () {
    $group1 = ListingGroup::factory()->pendingReview()->create(['match_score' => 0.9]);
    $group2 = ListingGroup::factory()->pendingReview()->create(['match_score' => 0.7]);

    Listing::factory()->count(2)->create([
        'platform_id' => $this->platform->id,
        'listing_group_id' => $group1->id,
        'dedup_status' => DedupStatus::Grouped,
    ]);
    Listing::factory()->count(2)->create([
        'platform_id' => $this->platform->id,
        'listing_group_id' => $group2->id,
        'dedup_status' => DedupStatus::Grouped,
    ]);

    Livewire::test(ReviewCandidates::class)
        ->assertSet('currentGroupId', $group1->id)
        ->call('selectGroup', $group2->id)
        ->assertSet('currentGroupId', $group2->id);
});

it('can approve a group for AI processing', function () {
    $group = ListingGroup::factory()->pendingReview()->create();
    Listing::factory()->count(2)->create([
        'platform_id' => $this->platform->id,
        'listing_group_id' => $group->id,
        'dedup_status' => DedupStatus::Grouped,
    ]);

    Livewire::test(ReviewCandidates::class)
        ->call('approveGroup');

    $group->refresh();
    expect($group->status)->toBe(ListingGroupStatus::PendingAi);
});

it('can reject a group', function () {
    $group = ListingGroup::factory()->pendingReview()->create();
    $listings = Listing::factory()->count(2)->create([
        'platform_id' => $this->platform->id,
        'listing_group_id' => $group->id,
        'dedup_status' => DedupStatus::Grouped,
    ]);

    Livewire::test(ReviewCandidates::class)
        ->set('rejectionReason', 'Different properties')
        ->call('rejectGroup');

    $group->refresh();
    expect($group->status)->toBe(ListingGroupStatus::Rejected)
        ->and($group->rejection_reason)->toBe('Different properties');

    // Listings should be reset to pending
    foreach ($listings as $listing) {
        $listing->refresh();
        expect($listing->listing_group_id)->toBeNull()
            ->and($listing->dedup_status)->toBe(DedupStatus::Pending);
    }
});

it('computes pending count correctly', function () {
    ListingGroup::factory()->count(3)->pendingReview()->create();
    ListingGroup::factory()->count(2)->pendingAi()->create();

    Livewire::test(ReviewCandidates::class)
        ->assertSee('3 pending');
});
