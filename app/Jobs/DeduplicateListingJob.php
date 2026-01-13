<?php

namespace App\Jobs;

use App\Enums\DedupStatus;
use App\Models\Listing;
use App\Services\Dedup\DeduplicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeduplicateListingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 2;

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [15, 30];
    }

    public function __construct(
        public int $listingId,
    ) {
        $this->onQueue('dedup');
    }

    public function handle(DeduplicationService $dedupService): void
    {
        $listing = Listing::find($this->listingId);

        if (! $listing) {
            Log::warning('DeduplicateListingJob: Listing not found', ['listing_id' => $this->listingId]);

            return;
        }

        if (! $listing->raw_data) {
            Log::warning('DeduplicateListingJob: Listing has no raw data', ['listing_id' => $this->listingId]);

            return;
        }

        $dedupService->processListing($listing);
    }

    /**
     * Handle job failure - reset status so it can be retried.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('DeduplicateListingJob failed', [
            'listing_id' => $this->listingId,
            'error' => $exception?->getMessage(),
        ]);

        Listing::where('id', $this->listingId)->update([
            'dedup_status' => DedupStatus::Failed,
        ]);
    }
}
