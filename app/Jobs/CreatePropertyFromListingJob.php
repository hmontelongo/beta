<?php

namespace App\Jobs;

use App\Enums\DedupStatus;
use App\Models\Listing;
use App\Services\AI\PropertyCreationService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Create a property directly from a unique listing (no group needed).
 * Used when a listing has no duplicates and should go straight to property creation.
 */
class CreatePropertyFromListingJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 180; // 3 minutes for AI processing

    public int $maxExceptions = 5;

    public function __construct(
        public int $listingId,
    ) {
        $this->onQueue('property-creation');
    }

    /**
     * Get the unique ID for the job.
     * Prevents duplicate jobs for the same listing.
     */
    public function uniqueId(): string
    {
        return 'create-property-listing-'.$this->listingId;
    }

    /**
     * How long the unique lock should be maintained.
     */
    public int $uniqueFor = 300; // 5 minutes

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
            (new ThrottlesExceptionsWithRedis(3, 30))
                ->by('claude-api-throttle')
                ->when(fn (Throwable $e) => str_contains($e->getMessage(), '429')),
        ];
    }

    public function handle(PropertyCreationService $propertyCreationService): void
    {
        $listing = Listing::find($this->listingId);

        if (! $listing) {
            Log::warning('CreatePropertyFromListingJob: Listing not found', [
                'listing_id' => $this->listingId,
            ]);

            return;
        }

        // Skip if not in Unique status (already processed or status changed)
        if ($listing->dedup_status !== DedupStatus::Unique) {
            Log::info('CreatePropertyFromListingJob: Listing not in unique status', [
                'listing_id' => $this->listingId,
                'status' => $listing->dedup_status->value,
            ]);

            return;
        }

        // Skip if already has a property
        if ($listing->property_id) {
            Log::info('CreatePropertyFromListingJob: Listing already has property', [
                'listing_id' => $this->listingId,
                'property_id' => $listing->property_id,
            ]);

            // Mark as completed
            $listing->update(['dedup_status' => DedupStatus::Completed]);

            return;
        }

        $propertyCreationService->createPropertyFromListing($listing);
    }

    /**
     * Handle job failure - reset status so it can be retried.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('CreatePropertyFromListingJob failed', [
            'listing_id' => $this->listingId,
            'error' => $exception?->getMessage(),
        ]);

        // Reset to Unique for retry
        Listing::where('id', $this->listingId)->update([
            'dedup_status' => DedupStatus::Unique,
        ]);
    }
}
