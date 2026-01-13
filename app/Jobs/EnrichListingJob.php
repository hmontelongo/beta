<?php

namespace App\Jobs;

use App\Enums\AiEnrichmentStatus;
use App\Models\Listing;
use App\Services\AI\ListingEnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnrichListingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    /**
     * Backoff times in seconds - longer delays for rate limit handling.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [60, 120, 180];
    }

    public function __construct(
        public int $listingId,
    ) {
        $this->onQueue('ai-enrichment');
    }

    public function handle(ListingEnrichmentService $enrichmentService): void
    {
        $listing = Listing::find($this->listingId);

        if (! $listing) {
            Log::warning('EnrichListingJob: Listing not found', ['listing_id' => $this->listingId]);

            return;
        }

        if (! $listing->raw_data) {
            Log::warning('EnrichListingJob: Listing has no raw data', ['listing_id' => $this->listingId]);

            return;
        }

        // Job owns state transition - set Processing at start of actual work
        $listing->update(['ai_status' => AiEnrichmentStatus::Processing]);

        $enrichmentService->enrichListing($listing);
    }

    /**
     * Handle job failure - reset status so it doesn't stay stuck.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('EnrichListingJob failed', [
            'listing_id' => $this->listingId,
            'error' => $exception?->getMessage(),
        ]);

        Listing::where('id', $this->listingId)->update([
            'ai_status' => AiEnrichmentStatus::Failed,
        ]);
    }
}
