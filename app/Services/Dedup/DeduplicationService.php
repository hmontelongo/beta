<?php

namespace App\Services\Dedup;

use App\Enums\DedupCandidateStatus;
use App\Enums\DedupStatus;
use App\Models\DedupCandidate;
use App\Models\Listing;
use Illuminate\Support\Facades\Log;

class DeduplicationService
{
    public function __construct(
        protected CandidateMatcherService $matcher,
        protected PropertyMergerService $merger,
    ) {}

    /**
     * Process a single listing for deduplication.
     */
    public function processListing(Listing $listing): void
    {
        if ($listing->property_id) {
            Log::debug('Listing already linked to property', ['listing_id' => $listing->id, 'property_id' => $listing->property_id]);

            // Already processed - don't change anything, just return
            return;
        }

        // Find candidates
        $candidates = $this->matcher->findCandidates($listing);

        // If no candidates found, create new property
        if ($candidates->isEmpty()) {
            $this->merger->createPropertyFromListing($listing);
            $listing->update([
                'dedup_status' => DedupStatus::New,
                'dedup_checked_at' => now(),
            ]);

            return;
        }

        // Check for auto-confirmed matches
        $autoMatches = $candidates->filter(
            fn (DedupCandidate $c) => $c->status === DedupCandidateStatus::ConfirmedMatch
        );

        if ($autoMatches->isNotEmpty()) {
            // Use the best auto-match
            $bestMatch = $autoMatches->sortByDesc('overall_score')->first();
            $this->resolveMatch($listing, $bestMatch);

            return;
        }

        // Check if any need review
        $needsReview = $candidates->contains(
            fn (DedupCandidate $c) => $c->status === DedupCandidateStatus::NeedsReview
        );

        if ($needsReview) {
            $listing->update([
                'dedup_status' => DedupStatus::NeedsReview,
                'dedup_checked_at' => now(),
            ]);

            return;
        }

        // All candidates are confirmed different - create new property
        $this->merger->createPropertyFromListing($listing);
        $listing->update([
            'dedup_status' => DedupStatus::New,
            'dedup_checked_at' => now(),
        ]);
    }

    /**
     * Resolve a confirmed match between listings.
     */
    public function resolveMatch(Listing $listing, DedupCandidate $candidate): void
    {
        // Get the other listing from the candidate
        $matchedListing = $candidate->listing_a_id === $listing->id
            ? $candidate->listingB
            : $candidate->listingA;

        // Check if matched listing already has a property
        if ($matchedListing->property_id) {
            // Merge into existing property
            $property = $matchedListing->property;
            $this->merger->mergeListingIntoProperty($listing, $property);
        } else {
            // Create new property from matched listing, then merge
            $property = $this->merger->createPropertyFromListing($matchedListing);
            $this->merger->mergeListingIntoProperty($listing, $property);
        }

        // Update candidate status
        $candidate->update([
            'status' => DedupCandidateStatus::ConfirmedMatch,
            'resolved_property_id' => $property->id,
            'resolved_at' => now(),
        ]);

        // Update both listings
        $listing->update([
            'dedup_status' => DedupStatus::Matched,
            'dedup_checked_at' => now(),
        ]);

        $matchedListing->update([
            'dedup_status' => DedupStatus::Matched,
            'dedup_checked_at' => now(),
        ]);

        Log::info('Dedup match resolved', [
            'listing_a' => $candidate->listing_a_id,
            'listing_b' => $candidate->listing_b_id,
            'property_id' => $property->id,
        ]);
    }

    /**
     * Mark a candidate as confirmed different (not a match).
     */
    public function rejectMatch(DedupCandidate $candidate): void
    {
        $candidate->update([
            'status' => DedupCandidateStatus::ConfirmedDifferent,
            'resolved_at' => now(),
        ]);

        // Check if either listing now needs a new property
        foreach ([$candidate->listingA, $candidate->listingB] as $listing) {
            if ($listing->property_id) {
                continue;
            }

            // Check if there are any other pending/review candidates
            $hasPendingCandidates = DedupCandidate::where(function ($q) use ($listing) {
                $q->where('listing_a_id', $listing->id)
                    ->orWhere('listing_b_id', $listing->id);
            })
                ->whereIn('status', [DedupCandidateStatus::NeedsReview])
                ->exists();

            if (! $hasPendingCandidates) {
                // Create new property for this listing
                $this->merger->createPropertyFromListing($listing);
                $listing->update([
                    'dedup_status' => DedupStatus::New,
                    'dedup_checked_at' => now(),
                ]);
            }
        }
    }

    /**
     * Get statistics for deduplication status.
     *
     * @return array{pending: int, matched: int, new: int, needs_review: int}
     */
    public function getStats(): array
    {
        return [
            'pending' => Listing::where('dedup_status', DedupStatus::Pending)->count(),
            'matched' => Listing::where('dedup_status', DedupStatus::Matched)->count(),
            'new' => Listing::where('dedup_status', DedupStatus::New)->count(),
            'needs_review' => Listing::where('dedup_status', DedupStatus::NeedsReview)->count(),
        ];
    }
}
