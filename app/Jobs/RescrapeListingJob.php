<?php

namespace App\Jobs;

use App\Enums\DedupStatus;
use App\Models\Listing;
use App\Services\ScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RescrapeListingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

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
        $this->onQueue('scraping');
    }

    public function handle(ScraperService $scraperService): void
    {
        $listing = Listing::findOrFail($this->listingId);

        Log::info('Re-scraping listing', [
            'listing_id' => $listing->id,
            'url' => $listing->original_url,
        ]);

        try {
            $data = $scraperService->scrapeListing($listing->original_url);

            // Mark property for re-analysis if this listing is linked to one
            if ($listing->property_id && $listing->property) {
                $listing->property->markForReanalysis();
            }

            $updateData = [
                'operations' => $data['operations'] ?? [],
                'external_codes' => $data['external_codes'] ?? null,
                'raw_data' => $data,
                'data_quality' => $data['data_quality'] ?? null,
                'scraped_at' => now(),
            ];

            // Only reset dedup if listing doesn't have a property
            // (listings with properties stay completed - property will be re-analyzed instead)
            if (! $listing->property_id) {
                $updateData['dedup_status'] = DedupStatus::Pending;
                $updateData['dedup_checked_at'] = null;
                $updateData['listing_group_id'] = null;
                $updateData['is_primary_in_group'] = false;
            }

            $listing->update($updateData);

            Log::info('Re-scrape completed', [
                'listing_id' => $listing->id,
                'images_count' => count($data['images'] ?? []),
                'has_property' => (bool) $listing->property_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Re-scrape failed', [
                'listing_id' => $this->listingId,
                'url' => $listing->original_url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('RescrapeListingJob failed permanently', [
            'listing_id' => $this->listingId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
