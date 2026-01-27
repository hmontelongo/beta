<?php

namespace App\Jobs;

use App\Enums\ListingGroupStatus;
use App\Models\ListingGroup;
use App\Services\AI\PropertyCreationService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreatePropertyFromListingsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 180; // 3 minutes for AI processing

    public int $maxExceptions = 5;

    public function __construct(
        public int $listingGroupId,
    ) {
        $this->onQueue('property-creation');
    }

    /**
     * Get the unique ID for the job.
     * Prevents duplicate jobs for the same listing group.
     */
    public function uniqueId(): string
    {
        return 'create-property-group-'.$this->listingGroupId;
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
        $group = ListingGroup::find($this->listingGroupId);

        if (! $group) {
            Log::warning('CreatePropertyFromListingsJob: ListingGroup not found', [
                'listing_group_id' => $this->listingGroupId,
            ]);

            return;
        }

        // Skip if already completed or rejected
        if ($group->status->isResolved()) {
            Log::info('CreatePropertyFromListingsJob: Group already resolved', [
                'listing_group_id' => $this->listingGroupId,
                'status' => $group->status->value,
            ]);

            return;
        }

        // Skip if not ready for AI (still pending review)
        if ($group->status === ListingGroupStatus::PendingReview) {
            Log::info('CreatePropertyFromListingsJob: Group still pending review', [
                'listing_group_id' => $this->listingGroupId,
            ]);

            return;
        }

        // Skip if already being processed (race condition prevention)
        if ($group->status === ListingGroupStatus::ProcessingAi) {
            Log::info('CreatePropertyFromListingsJob: Group already being processed', [
                'listing_group_id' => $this->listingGroupId,
            ]);

            return;
        }

        // Delete orphaned groups (no listings attached - can happen after re-scraping)
        if ($group->listings()->count() === 0) {
            Log::warning('CreatePropertyFromListingsJob: Deleting orphaned group with no listings', [
                'listing_group_id' => $this->listingGroupId,
            ]);
            $group->delete();

            return;
        }

        $propertyCreationService->createPropertyFromGroup($group);
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('CreatePropertyFromListingsJob failed', [
            'listing_group_id' => $this->listingGroupId,
            'error' => $exception?->getMessage(),
        ]);

        // Mark as Failed to prevent infinite retry loop
        // Admin can review and manually reset to PendingAi if retry is desired
        ListingGroup::where('id', $this->listingGroupId)->update([
            'status' => ListingGroupStatus::Failed,
            'rejection_reason' => 'AI processing failed after retries: '.($exception?->getMessage() ?? 'Unknown error'),
        ]);
    }
}
