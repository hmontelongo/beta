<?php

namespace App\Jobs;

use App\Enums\AiEnrichmentStatus;
use App\Models\Listing;
use App\Services\AI\ListingEnrichmentService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnrichListingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    // Use retryUntil instead of tries for rate-limited jobs
    public int $maxExceptions = 5;

    public function __construct(
        public int $listingId,
    ) {
        $this->onQueue('ai-enrichment');
    }

    /**
     * Determine the time at which the job should timeout.
     * Allow up to 2 hours for rate-limited retries.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHours(2);
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            // Throttle on 429 errors - after 3 consecutive rate limit errors, wait 30s
            // No rate limiter needed - with 1K req/min limit and ~10s per job,
            // even 10 workers only use ~60 req/min
            (new ThrottlesExceptionsWithRedis(3, 30))
                ->by('claude-api-throttle')
                ->when(fn (Throwable $e) => str_contains($e->getMessage(), '429')),
        ];
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
