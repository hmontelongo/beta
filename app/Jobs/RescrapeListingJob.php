<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\ScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

            $listing->update([
                'operations' => $data['operations'] ?? [],
                'external_codes' => $data['external_codes'] ?? null,
                'raw_data' => $data,
                'data_quality' => $data['data_quality'] ?? null,
                'scraped_at' => now(),
            ]);

            Log::info('Re-scrape completed', [
                'listing_id' => $listing->id,
                'images_count' => count($data['images'] ?? []),
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
}
