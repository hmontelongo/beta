<?php

namespace App\Services\Dedup;

use App\Enums\DedupCandidateStatus;
use App\Enums\DedupStatus;
use App\Enums\ListingGroupStatus;
use App\Jobs\DeduplicateListingJob;
use App\Models\DedupCandidate;
use App\Models\Listing;
use App\Models\ListingGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeduplicationService
{
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

        // Skip if already in a group (awaiting human review or AI processing)
        if ($listing->listing_group_id) {
            Log::debug('Listing already in a group', [
                'listing_id' => $listing->id,
                'listing_group_id' => $listing->listing_group_id,
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

        // No matches - mark as unique for direct property creation
        if ($candidates->isEmpty()) {
            $this->markListingAsUnique($listing);

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

        // All candidates are confirmed different - mark as unique for direct property creation
        $this->markListingAsUnique($listing);
    }

    /**
     * Mark a listing as unique (no matches found) for direct property creation.
     */
    protected function markListingAsUnique(Listing $listing): void
    {
        $listing->update([
            'dedup_status' => DedupStatus::Unique,
            'dedup_checked_at' => now(),
        ]);

        Log::info('Listing marked as unique for direct property creation', [
            'listing_id' => $listing->id,
        ]);
    }

    /**
     * Create or add to a listing group for a confirmed match.
     * ConfirmedMatch candidates (>= auto_match_threshold) always go directly to AI processing.
     */
    protected function addToMatchGroup(Listing $listing, DedupCandidate $candidate): void
    {
        $this->addListingToGroupWithCandidate($listing, $candidate, ListingGroupStatus::PendingAi);

        $candidate->update([
            'status' => DedupCandidateStatus::ConfirmedMatch,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Create a listing group for human review containing both potential matches.
     * Both listings are placed in the same group so the reviewer can compare them side-by-side.
     * Uses transaction with row-level locking to prevent race conditions with parallel workers.
     */
    protected function createReviewGroup(Listing $listing, DedupCandidate $candidate): void
    {
        DB::transaction(function () use ($listing, $candidate) {
            $matchedListingId = $candidate->listing_a_id === $listing->id
                ? $candidate->listing_b_id
                : $candidate->listing_a_id;

            // Lock both listings in consistent order (lower ID first) to prevent deadlocks
            // Use the locked rows directly to ensure we have the latest state
            $lockIds = [$listing->id, $matchedListingId];
            sort($lockIds);
            $lockedListings = Listing::whereIn('id', $lockIds)->lockForUpdate()->get()->keyBy('id');

            $matchedListing = $lockedListings->get($matchedListingId);
            $currentListing = $lockedListings->get($listing->id);

            if (! $matchedListing) {
                Log::warning('Matched listing not found', ['listing_id' => $matchedListingId]);

                return;
            }

            // Re-check if current listing was already grouped by another worker
            if ($currentListing->listing_group_id) {
                Log::debug('Listing already grouped by another worker', [
                    'listing_id' => $listing->id,
                    'listing_group_id' => $currentListing->listing_group_id,
                ]);

                return;
            }

            // If matched listing already has a completed group (property exists), don't disturb it
            // Create a standalone group for the new listing with reference to the matched property
            if ($matchedListing->listing_group_id) {
                $existingGroup = ListingGroup::lockForUpdate()->find($matchedListing->listing_group_id);
                if ($existingGroup && $existingGroup->status === ListingGroupStatus::Completed) {
                    $existingGroup->property->markForReanalysis();

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

                // Matched listing has a non-completed group - check if new listing matches ALL members
                if ($this->matchesAllGroupMembers($listing, $existingGroup)) {
                    $existingGroup->update([
                        'status' => ListingGroupStatus::PendingReview,
                        'match_score' => min($existingGroup->match_score ?? 1.0, $candidate->overall_score),
                    ]);
                    $this->markListingAsGrouped($listing, $existingGroup->id, isPrimary: false);

                    Log::info('Added listing to existing review group (validated all members)', [
                        'listing_id' => $listing->id,
                        'listing_group_id' => $existingGroup->id,
                        'matched_listing_id' => $matchedListing->id,
                        'match_score' => $candidate->overall_score,
                    ]);
                } else {
                    // Doesn't match all members - wait for group to resolve
                    $this->markListingAsWaiting($listing, $existingGroup->id);
                }

                return;
            }

            // Check if matched listing has a property directly (unique listing that went to property creation)
            if ($matchedListing->property_id) {
                $matchedListing->property->markForReanalysis();

                $group = ListingGroup::create([
                    'status' => ListingGroupStatus::PendingReview,
                    'match_score' => $candidate->overall_score,
                    'matched_property_id' => $matchedListing->property_id,
                ]);
                $this->markListingAsGrouped($listing, $group->id, isPrimary: true);

                Log::info('Created review group for listing matching property without group', [
                    'listing_id' => $listing->id,
                    'listing_group_id' => $group->id,
                    'matched_listing_id' => $matchedListing->id,
                    'matched_property_id' => $matchedListing->property_id,
                    'match_score' => $candidate->overall_score,
                ]);

                return;
            }

            // Neither listing has a group or property - create new group with BOTH listings
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
     * Uses transaction with row-level locking to prevent race conditions with parallel workers.
     */
    protected function addListingToGroupWithCandidate(
        Listing $listing,
        DedupCandidate $candidate,
        ListingGroupStatus $newGroupStatus
    ): void {
        DB::transaction(function () use ($listing, $candidate, $newGroupStatus) {
            $matchedListingId = $candidate->listing_a_id === $listing->id
                ? $candidate->listing_b_id
                : $candidate->listing_a_id;

            // Lock both listings in consistent order (lower ID first) to prevent deadlocks
            // Use the locked rows directly to ensure we have the latest state
            $lockIds = [$listing->id, $matchedListingId];
            sort($lockIds);
            $lockedListings = Listing::whereIn('id', $lockIds)->lockForUpdate()->get()->keyBy('id');

            $matchedListing = $lockedListings->get($matchedListingId);
            $currentListing = $lockedListings->get($listing->id);

            // Re-check if current listing was already grouped by another worker
            if ($currentListing->listing_group_id) {
                Log::debug('Listing already grouped by another worker', [
                    'listing_id' => $listing->id,
                    'listing_group_id' => $currentListing->listing_group_id,
                ]);

                return;
            }

            $existingGroup = $matchedListing->listing_group_id
                ? ListingGroup::lockForUpdate()->find($matchedListing->listing_group_id)
                : null;

            if ($existingGroup) {
                // For completed groups, existing logic is fine (property reanalysis)
                if ($existingGroup->status === ListingGroupStatus::Completed) {
                    $this->handleCompletedGroupReanalysis($existingGroup);
                    $this->markListingAsGrouped($listing, $existingGroup->id, isPrimary: false);

                    Log::info('Added listing to completed group for reanalysis', [
                        'listing_id' => $listing->id,
                        'listing_group_id' => $existingGroup->id,
                        'matched_listing_id' => $matchedListing->id,
                    ]);

                    return;
                }

                // For pending groups, validate all members
                if ($this->matchesAllGroupMembers($listing, $existingGroup)) {
                    $this->markListingAsGrouped($listing, $existingGroup->id, isPrimary: false);

                    Log::info('Added listing to existing group (validated all members)', [
                        'listing_id' => $listing->id,
                        'listing_group_id' => $existingGroup->id,
                        'matched_listing_id' => $matchedListing->id,
                    ]);
                } else {
                    $this->markListingAsWaiting($listing, $existingGroup->id);
                }

                return;
            }

            // Check if matched listing has a property directly (unique listing that went to property creation)
            if ($matchedListing->property_id) {
                // Mark matched property for reanalysis since we found a potential duplicate
                $matchedListing->property->markForReanalysis();

                $group = ListingGroup::create([
                    'status' => $newGroupStatus,
                    'match_score' => $candidate->overall_score,
                    'matched_property_id' => $matchedListing->property_id,
                ]);
                $this->markListingAsGrouped($listing, $group->id, isPrimary: true);

                Log::info('Created group for listing matching property without group', [
                    'listing_id' => $listing->id,
                    'listing_group_id' => $group->id,
                    'matched_listing_id' => $matchedListing->id,
                    'matched_property_id' => $matchedListing->property_id,
                    'match_score' => $candidate->overall_score,
                ]);

                return;
            }

            // Neither listing has a group or property - create new group with BOTH listings
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
        });
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
     * Check if a listing has passing candidate scores with ALL members of a group.
     * Uses a single query instead of N queries for efficiency.
     */
    protected function matchesAllGroupMembers(Listing $listing, ListingGroup $group): bool
    {
        $memberIds = $group->listings()->pluck('id')->toArray();
        $requiredMatches = count($memberIds);

        if ($requiredMatches === 0) {
            return true;
        }

        $reviewThreshold = config('services.dedup.review_threshold', 0.73);

        // Count candidates that pass: score >= threshold AND not confirmed different
        $passingCount = DedupCandidate::where(function ($q) use ($listing, $memberIds) {
            $q->where('listing_a_id', $listing->id)->whereIn('listing_b_id', $memberIds);
        })->orWhere(function ($q) use ($listing, $memberIds) {
            $q->whereIn('listing_a_id', $memberIds)->where('listing_b_id', $listing->id);
        })
            ->where('overall_score', '>=', $reviewThreshold)
            ->where('status', '!=', DedupCandidateStatus::ConfirmedDifferent)
            ->count();

        return $passingCount === $requiredMatches;
    }

    /**
     * Mark a listing as waiting for busy matches to resolve.
     */
    protected function markListingAsWaiting(Listing $listing, int $groupId): void
    {
        $listing->update([
            'dedup_status' => DedupStatus::Waiting,
            'dedup_checked_at' => now(),
            'waiting_for_group_id' => $groupId,
        ]);

        Log::info('Listing marked as waiting', [
            'listing_id' => $listing->id,
            'waiting_for_group_id' => $groupId,
        ]);
    }

    /**
     * Reset listings that were waiting for a group to resolve.
     * They go back to pending and are immediately queued for re-processing.
     */
    protected function resetWaitingListings(int $groupId): void
    {
        $waitingListings = Listing::where('waiting_for_group_id', $groupId)
            ->where('dedup_status', DedupStatus::Waiting)
            ->get();

        if ($waitingListings->isEmpty()) {
            return;
        }

        // Reset all to pending
        Listing::whereIn('id', $waitingListings->pluck('id'))
            ->update([
                'dedup_status' => DedupStatus::Pending,
                'waiting_for_group_id' => null,
                'dedup_checked_at' => null,
            ]);

        // Dispatch jobs immediately for each waiting listing
        foreach ($waitingListings as $listing) {
            DeduplicateListingJob::dispatch($listing->id);
        }

        Log::info('Reset and re-queued waiting listings after group resolved', [
            'group_id' => $groupId,
            'count' => $waitingListings->count(),
            'listing_ids' => $waitingListings->pluck('id')->toArray(),
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

        // Reset any listings waiting for this group to resolve
        $this->resetWaitingListings($group->id);

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

            // Mark candidates between group listings as confirmed different
            $this->markCandidatesAsConfirmedDifferent($listingIds, $listingIds);

            // If this group was matching against an existing property,
            // mark candidates against ALL that property's listings as confirmed different
            // (prevents the same listing from matching a different listing of the same property)
            if ($group->matched_property_id) {
                $propertyListingIds = Listing::where('property_id', $group->matched_property_id)
                    ->pluck('id')
                    ->toArray();
                $this->markCandidatesAsConfirmedDifferent($listingIds, $propertyListingIds);
            }

            $this->resetListingsToPending($listingIds);

            $group->reject($reason);

            // Reset any listings waiting for this group to resolve
            $this->resetWaitingListings($group->id);
        });

        Log::info('Listing group rejected', [
            'listing_group_id' => $group->id,
            'reason' => $reason,
            'matched_property_id' => $group->matched_property_id,
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
            // If group has matched_property_id, keep it (valid comparison to existing property)
            if ($group->matched_property_id) {
                Log::info('Group kept with single listing due to matched_property_id', [
                    'listing_group_id' => $group->id,
                    'remaining_listing_id' => $remainingListingIds[0],
                    'matched_property_id' => $group->matched_property_id,
                ]);

                return;
            }

            // No matched_property_id - dissolve group and reset listing to pending
            $this->resetListingsToPending($remainingListingIds);
            $group->delete();

            Log::info('Group dissolved after removal - listing reset to pending', [
                'listing_group_id' => $group->id,
                'reset_listing_id' => $remainingListingIds[0],
            ]);
        }
    }

    /**
     * Get statistics for deduplication status.
     *
     * @return array{pending: int, grouped: int, unique: int, completed: int, groups_pending_review: int, groups_pending_ai: int}
     */
    public function getStats(): array
    {
        return [
            'pending' => Listing::pendingDedup()->count(),
            'grouped' => Listing::grouped()->count(),
            'unique' => Listing::unique()->count(),
            'completed' => Listing::completed()->count(),
            'groups_pending_review' => ListingGroup::pendingReview()->count(),
            'groups_pending_ai' => ListingGroup::pendingAi()->count(),
        ];
    }
}
