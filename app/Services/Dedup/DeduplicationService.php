<?php

namespace App\Services\Dedup;

use App\Enums\DedupCandidateStatus;
use App\Enums\DedupStatus;
use App\Enums\ListingGroupStatus;
use App\Models\DedupCandidate;
use App\Models\Listing;
use App\Models\ListingGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeduplicationService
{
    /**
     * Confidence threshold for auto-confirming matches.
     * Matches above this score go directly to AI processing.
     * Matches below require human review.
     */
    protected const HIGH_CONFIDENCE_THRESHOLD = 0.85;

    public function __construct(
        protected CandidateMatcherService $matcher,
    ) {}

    /**
     * Process a single listing for deduplication.
     * Creates listing groups instead of properties directly.
     */
    public function processListing(Listing $listing): void
    {
        // Skip if not geocoded yet - must wait for geocoding to complete
        if ($listing->geocode_status !== 'success') {
            Log::debug('Skipping dedup - not geocoded yet', [
                'listing_id' => $listing->id,
                'geocode_status' => $listing->geocode_status,
            ]);

            return;
        }

        // Skip if no coordinates even after successful geocode (shouldn't happen, but defensive)
        if (! $listing->latitude || ! $listing->longitude) {
            Log::warning('Listing marked as geocoded but missing coordinates', [
                'listing_id' => $listing->id,
            ]);

            return;
        }

        // If listing already has a property and that property needs reanalysis,
        // it will be handled by ProcessPropertyCreationBatchJob
        if ($listing->property_id) {
            Log::debug('Listing already linked to property', [
                'listing_id' => $listing->id,
                'property_id' => $listing->property_id,
            ]);

            if ($listing->dedup_status === DedupStatus::Processing) {
                $listing->update([
                    'dedup_status' => DedupStatus::Completed,
                    'dedup_checked_at' => now(),
                ]);
            }

            return;
        }

        // Find potential matches
        $candidates = $this->matcher->findCandidates($listing);

        // No matches - create a group for this unique listing
        if ($candidates->isEmpty()) {
            $this->createSingleListingGroup($listing);

            return;
        }

        // Check for high-confidence auto-confirmed matches
        $autoMatches = $candidates->filter(
            fn (DedupCandidate $c) => $c->status === DedupCandidateStatus::ConfirmedMatch
        );

        if ($autoMatches->isNotEmpty()) {
            // Use the best auto-match
            $bestMatch = $autoMatches->sortByDesc('overall_score')->first();
            $this->addToMatchGroup($listing, $bestMatch);

            return;
        }

        // Check if any need human review
        $needsReview = $candidates->contains(
            fn (DedupCandidate $c) => $c->status === DedupCandidateStatus::NeedsReview
        );

        if ($needsReview) {
            // Get the best candidate requiring review
            $bestCandidate = $candidates
                ->filter(fn (DedupCandidate $c) => $c->status === DedupCandidateStatus::NeedsReview)
                ->sortByDesc('overall_score')
                ->first();

            $this->createReviewGroup($listing, $bestCandidate);

            return;
        }

        // All candidates are confirmed different - create unique listing group
        $this->createSingleListingGroup($listing);
    }

    /**
     * Create a listing group for a unique listing with no matches.
     */
    protected function createSingleListingGroup(Listing $listing): void
    {
        DB::transaction(function () use ($listing) {
            $group = ListingGroup::create([
                'status' => ListingGroupStatus::PendingAi, // High confidence for unique
                'match_score' => null, // No match
            ]);

            $listing->update([
                'listing_group_id' => $group->id,
                'is_primary_in_group' => true,
                'dedup_status' => DedupStatus::Grouped,
                'dedup_checked_at' => now(),
            ]);

            Log::info('Created unique listing group', [
                'listing_id' => $listing->id,
                'listing_group_id' => $group->id,
            ]);
        });
    }

    /**
     * Create or add to a listing group for a confirmed match.
     */
    protected function addToMatchGroup(Listing $listing, DedupCandidate $candidate): void
    {
        $isHighConfidence = $candidate->overall_score >= self::HIGH_CONFIDENCE_THRESHOLD;
        $status = $isHighConfidence ? ListingGroupStatus::PendingAi : ListingGroupStatus::PendingReview;

        DB::transaction(function () use ($listing, $candidate, $status) {
            $this->addListingToGroupWithCandidate($listing, $candidate, $status);

            $candidate->update([
                'status' => DedupCandidateStatus::ConfirmedMatch,
                'resolved_at' => now(),
            ]);
        });
    }

    /**
     * Create a listing group for human review containing both potential matches.
     * Both listings are placed in the same group so the reviewer can compare them side-by-side.
     */
    protected function createReviewGroup(Listing $listing, DedupCandidate $candidate): void
    {
        DB::transaction(function () use ($listing, $candidate) {
            $matchedListing = $candidate->listing_a_id === $listing->id
                ? $candidate->listingB
                : $candidate->listingA;

            // If matched listing already has a completed group (property exists), don't disturb it
            // Create a standalone group for the new listing with reference to the matched property
            if ($matchedListing->listing_group_id) {
                $existingGroup = ListingGroup::find($matchedListing->listing_group_id);
                if ($existingGroup && $existingGroup->status === ListingGroupStatus::Completed) {
                    $group = ListingGroup::create([
                        'status' => ListingGroupStatus::PendingReview,
                        'match_score' => $candidate->overall_score,
                        'matched_property_id' => $existingGroup->property_id,
                    ]);
                    $this->markListingAsGrouped($listing, $group->id, isPrimary: true);

                    Log::info('Created review group for listing matching completed property', [
                        'listing_id' => $listing->id,
                        'listing_group_id' => $group->id,
                        'matched_listing_id' => $matchedListing->id,
                        'matched_property_id' => $existingGroup->property_id,
                        'match_score' => $candidate->overall_score,
                    ]);

                    return;
                }

                // Matched listing has a non-completed group - add new listing to that group
                $existingGroup->update([
                    'status' => ListingGroupStatus::PendingReview,
                    'match_score' => $candidate->overall_score,
                ]);
                $this->markListingAsGrouped($listing, $existingGroup->id, isPrimary: false);

                Log::info('Added listing to existing review group', [
                    'listing_id' => $listing->id,
                    'listing_group_id' => $existingGroup->id,
                    'matched_listing_id' => $matchedListing->id,
                    'match_score' => $candidate->overall_score,
                ]);

                return;
            }

            // Neither listing has a group - create new group with BOTH listings
            $group = ListingGroup::create([
                'status' => ListingGroupStatus::PendingReview,
                'match_score' => $candidate->overall_score,
            ]);

            $this->markListingAsGrouped($matchedListing, $group->id, isPrimary: true);
            $this->markListingAsGrouped($listing, $group->id, isPrimary: false);

            Log::info('Created review group with both listings', [
                'listing_group_id' => $group->id,
                'listing_ids' => [$matchedListing->id, $listing->id],
                'match_score' => $candidate->overall_score,
            ]);
        });
    }

    /**
     * Add a listing to an existing or new group based on a candidate match.
     */
    protected function addListingToGroupWithCandidate(
        Listing $listing,
        DedupCandidate $candidate,
        ListingGroupStatus $newGroupStatus
    ): void {
        $matchedListing = $candidate->listing_a_id === $listing->id
            ? $candidate->listingB
            : $candidate->listingA;

        $existingGroup = $matchedListing->listing_group_id
            ? ListingGroup::lockForUpdate()->find($matchedListing->listing_group_id)
            : null;

        if ($existingGroup) {
            $this->handleCompletedGroupReanalysis($existingGroup);
            $this->markListingAsGrouped($listing, $existingGroup->id, isPrimary: false);

            Log::info('Added listing to existing group', [
                'listing_id' => $listing->id,
                'listing_group_id' => $existingGroup->id,
                'matched_listing_id' => $matchedListing->id,
            ]);

            return;
        }

        $group = ListingGroup::create([
            'status' => $newGroupStatus,
            'match_score' => $candidate->overall_score,
        ]);

        $this->markListingAsGrouped($matchedListing, $group->id, isPrimary: true);
        $this->markListingAsGrouped($listing, $group->id, isPrimary: false);

        Log::info('Created listing group', [
            'listing_group_id' => $group->id,
            'listing_ids' => [$matchedListing->id, $listing->id],
            'match_score' => $candidate->overall_score,
            'status' => $group->status->value,
        ]);
    }

    /**
     * Mark a completed group for re-analysis when new listings are added.
     */
    protected function handleCompletedGroupReanalysis(ListingGroup $group): void
    {
        if ($group->status === ListingGroupStatus::Completed && $group->property_id) {
            $group->property->markForReanalysis();
            $group->update(['status' => ListingGroupStatus::PendingAi]);
        }
    }

    /**
     * Update a listing to mark it as part of a group.
     */
    protected function markListingAsGrouped(Listing $listing, int $groupId, bool $isPrimary): void
    {
        $listing->update([
            'listing_group_id' => $groupId,
            'is_primary_in_group' => $isPrimary,
            'dedup_status' => DedupStatus::Grouped,
            'dedup_checked_at' => now(),
        ]);
    }

    /**
     * Approve a listing group from human review.
     */
    public function approveGroup(ListingGroup $group): void
    {
        if ($group->status !== ListingGroupStatus::PendingReview) {
            Log::warning('Cannot approve group not in pending_review status', [
                'listing_group_id' => $group->id,
                'status' => $group->status->value,
            ]);

            return;
        }

        $group->approve();

        // Update dedup candidates to confirmed match
        $listingIds = $group->listings()->pluck('id')->toArray();

        DedupCandidate::whereIn('listing_a_id', $listingIds)
            ->whereIn('listing_b_id', $listingIds)
            ->where('status', DedupCandidateStatus::NeedsReview)
            ->update([
                'status' => DedupCandidateStatus::ConfirmedMatch,
                'resolved_at' => now(),
            ]);

        Log::info('Listing group approved', ['listing_group_id' => $group->id]);
    }

    /**
     * Reject a listing group from human review.
     * Listings go back to pending for re-processing.
     */
    public function rejectGroup(ListingGroup $group, ?string $reason = null): void
    {
        if ($group->status !== ListingGroupStatus::PendingReview) {
            Log::warning('Cannot reject group not in pending_review status', [
                'listing_group_id' => $group->id,
                'status' => $group->status->value,
            ]);

            return;
        }

        DB::transaction(function () use ($group, $reason) {
            $listingIds = $group->listings()->pluck('id')->toArray();

            $this->markCandidatesAsConfirmedDifferent($listingIds, $listingIds);
            $this->resetListingsToPending($listingIds);

            $group->reject($reason);
        });

        Log::info('Listing group rejected', [
            'listing_group_id' => $group->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Remove a listing from a group during review.
     * The listing will be re-processed as a separate entity.
     */
    public function removeListingFromGroup(ListingGroup $group, Listing $listing): void
    {
        if ($listing->listing_group_id !== $group->id) {
            Log::warning('Listing does not belong to this group', [
                'listing_id' => $listing->id,
                'group_id' => $group->id,
            ]);

            return;
        }

        DB::transaction(function () use ($group, $listing) {
            $remainingListingIds = $group->listings()
                ->where('id', '!=', $listing->id)
                ->pluck('id')
                ->toArray();

            $this->markCandidatesAsConfirmedDifferent([$listing->id], $remainingListingIds);
            $this->resetListingsToPending([$listing->id]);
            $this->handleGroupAfterListingRemoval($group, $remainingListingIds);

            Log::info('Listing removed from group', [
                'listing_id' => $listing->id,
                'listing_group_id' => $group->id,
                'remaining_count' => count($remainingListingIds),
            ]);
        });
    }

    /**
     * Mark dedup candidates between two sets of listings as confirmed different.
     *
     * @param  array<int>  $listingIdsA
     * @param  array<int>  $listingIdsB
     */
    protected function markCandidatesAsConfirmedDifferent(array $listingIdsA, array $listingIdsB): void
    {
        DedupCandidate::where(function ($q) use ($listingIdsA, $listingIdsB) {
            $q->whereIn('listing_a_id', $listingIdsA)
                ->whereIn('listing_b_id', $listingIdsB);
        })->orWhere(function ($q) use ($listingIdsA, $listingIdsB) {
            $q->whereIn('listing_a_id', $listingIdsB)
                ->whereIn('listing_b_id', $listingIdsA);
        })->where('status', DedupCandidateStatus::NeedsReview)
            ->update([
                'status' => DedupCandidateStatus::ConfirmedDifferent,
                'resolved_at' => now(),
            ]);
    }

    /**
     * Reset listings to pending status for re-processing.
     *
     * @param  array<int>  $listingIds
     */
    protected function resetListingsToPending(array $listingIds): void
    {
        Listing::whereIn('id', $listingIds)->update([
            'listing_group_id' => null,
            'is_primary_in_group' => false,
            'dedup_status' => DedupStatus::Pending,
            'dedup_checked_at' => null,
        ]);
    }

    /**
     * Handle group state after a listing has been removed.
     *
     * @param  array<int>  $remainingListingIds
     */
    protected function handleGroupAfterListingRemoval(ListingGroup $group, array $remainingListingIds): void
    {
        $remainingCount = count($remainingListingIds);

        if ($remainingCount === 0) {
            $group->delete();
            Log::info('Empty group deleted', ['listing_group_id' => $group->id]);

            return;
        }

        if ($remainingCount === 1) {
            $group->update([
                'status' => ListingGroupStatus::PendingAi,
                'match_score' => null,
            ]);

            Log::info('Group converted to unique after removal', [
                'listing_group_id' => $group->id,
                'remaining_listing_id' => $remainingListingIds[0],
            ]);
        }
    }

    /**
     * Get statistics for deduplication status.
     *
     * @return array{pending: int, grouped: int, completed: int, groups_pending_review: int, groups_pending_ai: int}
     */
    public function getStats(): array
    {
        return [
            'pending' => Listing::pendingDedup()->count(),
            'grouped' => Listing::grouped()->count(),
            'completed' => Listing::completed()->count(),
            'groups_pending_review' => ListingGroup::pendingReview()->count(),
            'groups_pending_ai' => ListingGroup::pendingAi()->count(),
        ];
    }
}
