<?php

namespace App\Jobs;

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Models\DiscoveredListing;
use App\Models\Listing;
use App\Models\ScrapeJob;
use App\Services\ScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScrapeListingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $discoveredListingId,
    ) {}

    public function handle(ScraperService $scraperService): void
    {
        $discoveredListing = DiscoveredListing::findOrFail($this->discoveredListingId);

        $discoveredListing->update([
            'status' => DiscoveredListingStatus::Queued,
        ]);

        $scrapeJob = ScrapeJob::create([
            'platform_id' => $discoveredListing->platform_id,
            'discovered_listing_id' => $discoveredListing->id,
            'target_url' => $discoveredListing->url,
            'job_type' => ScrapeJobType::Listing,
            'status' => ScrapeJobStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $data = $scraperService->scrapeListing($discoveredListing->url);

            $listing = Listing::create([
                'platform_id' => $discoveredListing->platform_id,
                'discovered_listing_id' => $discoveredListing->id,
                'external_id' => $data['external_id'] ?? $discoveredListing->external_id,
                'original_url' => $discoveredListing->url,
                'operations' => $data['operations'] ?? [],
                'external_codes' => $data['external_codes'] ?? null,
                'raw_data' => $data,
                'data_quality' => $data['data_quality'] ?? null,
                'scraped_at' => now(),
            ]);

            $discoveredListing->update([
                'status' => DiscoveredListingStatus::Scraped,
                'attempts' => $discoveredListing->attempts + 1,
                'last_attempt_at' => now(),
            ]);

            $scrapeJob->update([
                'status' => ScrapeJobStatus::Completed,
                'completed_at' => now(),
                'result' => [
                    'listing_id' => $listing->id,
                    'external_id' => $listing->external_id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Listing scrape failed', [
                'discovered_listing_id' => $this->discoveredListingId,
                'url' => $discoveredListing->url,
                'error' => $e->getMessage(),
            ]);

            $discoveredListing->update([
                'status' => DiscoveredListingStatus::Failed,
                'attempts' => $discoveredListing->attempts + 1,
                'last_attempt_at' => now(),
            ]);

            $scrapeJob->update([
                'status' => ScrapeJobStatus::Failed,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
