<?php

namespace App\Jobs;

use App\Enums\DedupStatus;
use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Events\ScrapingCompleted;
use App\Jobs\Concerns\ChecksRunStatus;
use App\Models\DiscoveredListing;
use App\Models\Listing;
use App\Models\ScrapeJob;
use App\Models\ScrapeRun;
use App\Services\PublisherExtractionService;
use App\Services\ScrapeOrchestrator;
use App\Services\ScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScrapeListingJob implements ShouldQueue
{
    use ChecksRunStatus, Queueable;

    public int $timeout = 180; // 3 minutes for browser scraping

    public int $tries = 3;

    /**
     * Exponential backoff: 30s, 60s, 120s between retries.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(
        public int $discoveredListingId,
        public ?int $scrapeRunId = null,
    ) {
        $this->onQueue('scraping');
    }

    public function handle(
        ScraperService $scraperService,
        ScrapeOrchestrator $orchestrator,
        PublisherExtractionService $publisherExtractor
    ): void {
        if (! $this->isRunActive($this->scrapeRunId)) {
            return;
        }

        $discoveredListing = DiscoveredListing::findOrFail($this->discoveredListingId);
        $scrapeRun = $this->scrapeRunId ? ScrapeRun::find($this->scrapeRunId) : null;

        $scrapeJob = ScrapeJob::create([
            'platform_id' => $discoveredListing->platform_id,
            'scrape_run_id' => $this->scrapeRunId,
            'discovered_listing_id' => $discoveredListing->id,
            'target_url' => $discoveredListing->url,
            'job_type' => ScrapeJobType::Listing,
            'status' => ScrapeJobStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $data = $scraperService->scrapeListing($discoveredListing->url);

            // Check if listing already exists
            $existingListing = Listing::where('platform_id', $discoveredListing->platform_id)
                ->where('external_id', $data['external_id'] ?? $discoveredListing->external_id)
                ->first();

            $isRescrape = $existingListing !== null;

            $listing = Listing::updateOrCreate(
                [
                    'platform_id' => $discoveredListing->platform_id,
                    'external_id' => $data['external_id'] ?? $discoveredListing->external_id,
                ],
                [
                    'discovered_listing_id' => $discoveredListing->id,
                    'original_url' => $discoveredListing->url,
                    'operations' => $data['operations'] ?? [],
                    'external_codes' => $data['external_codes'] ?? null,
                    'raw_data' => $data,
                    'data_quality' => $data['data_quality'] ?? null,
                    'scraped_at' => now(),
                    // Set dedup_status for new listings
                    'dedup_status' => $isRescrape
                        ? $existingListing->dedup_status
                        : DedupStatus::Pending,
                ]
            );

            // Extract publisher from listing data
            $publisherExtractor->extractFromListing($listing);

            // For re-scrapes: mark property for re-analysis if data may have changed
            if ($isRescrape && $listing->property_id) {
                $listing->property->markForReanalysis();

                Log::info('Re-scraped listing, marked property for re-analysis', [
                    'listing_id' => $listing->id,
                    'property_id' => $listing->property_id,
                ]);
            }

            $discoveredListing->increment('attempts', 1, [
                'status' => DiscoveredListingStatus::Scraped,
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

            if ($scrapeRun) {
                // Stats are now computed from actual records - no incrementStat needed

                if ($orchestrator->checkScrapingComplete($scrapeRun->fresh())) {
                    ScrapingCompleted::dispatch($scrapeRun->fresh());
                }
            }
        } catch (\Throwable $e) {
            Log::error('Listing scrape failed', [
                'discovered_listing_id' => $this->discoveredListingId,
                'url' => $discoveredListing->url,
                'error' => $e->getMessage(),
            ]);

            $discoveredListing->increment('attempts', 1, [
                'status' => DiscoveredListingStatus::Failed,
                'last_attempt_at' => now(),
            ]);

            $scrapeJob->update([
                'status' => ScrapeJobStatus::Failed,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            // Stats are now computed from actual records - no incrementStat needed

            throw $e;
        }
    }

    /**
     * Handle permanent job failure after all retries exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ScrapeListingJob failed permanently', [
            'discovered_listing_id' => $this->discoveredListingId,
            'scrape_run_id' => $this->scrapeRunId,
            'error' => $exception?->getMessage(),
        ]);

        // Ensure discovered listing is marked as failed
        DiscoveredListing::where('id', $this->discoveredListingId)
            ->update([
                'status' => DiscoveredListingStatus::Failed,
                'last_attempt_at' => now(),
            ]);

        // Stats are computed from actual records (DiscoveredListing.status = Failed)
        // No manual incrementStat needed - the failed record is the source of truth
    }
}
