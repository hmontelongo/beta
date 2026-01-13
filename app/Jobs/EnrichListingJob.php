<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\AI\ListingEnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnrichListingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [30, 60];
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

        $enrichmentService->enrichListing($listing);
    }
}
