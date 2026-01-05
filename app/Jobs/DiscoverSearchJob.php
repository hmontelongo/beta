<?php

namespace App\Jobs;

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Models\DiscoveredListing;
use App\Models\Platform;
use App\Models\ScrapeJob;
use App\Services\ScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscoverSearchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $platformId,
        public string $searchUrl,
    ) {}

    public function handle(ScraperService $scraperService): void
    {
        $platform = Platform::findOrFail($this->platformId);

        $scrapeJob = ScrapeJob::create([
            'platform_id' => $this->platformId,
            'target_url' => $this->searchUrl,
            'job_type' => ScrapeJobType::Discovery,
            'status' => ScrapeJobStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $result = $scraperService->discoverPage($this->searchUrl, 1);

            $scrapeJob->update([
                'total_results' => $result['total_results'],
                'total_pages' => $result['total_pages'],
                'current_page' => 1,
            ]);

            $this->storeListings($platform, $result['listings'], $scrapeJob->id);

            for ($page = 2; $page <= $result['total_pages']; $page++) {
                DiscoverPageJob::dispatch($scrapeJob->id, $this->searchUrl, $page);
            }

            $scrapeJob->update([
                'status' => ScrapeJobStatus::Completed,
                'completed_at' => now(),
                'result' => [
                    'page_1_listings' => count($result['listings']),
                    'pages_dispatched' => max(0, $result['total_pages'] - 1),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Discovery search failed', [
                'platform_id' => $this->platformId,
                'search_url' => $this->searchUrl,
                'error' => $e->getMessage(),
            ]);

            $scrapeJob->update([
                'status' => ScrapeJobStatus::Failed,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<array{url: string, external_id: string|null}>  $listings
     */
    protected function storeListings(Platform $platform, array $listings, int $batchId): void
    {
        foreach ($listings as $listing) {
            DiscoveredListing::firstOrCreate(
                [
                    'platform_id' => $platform->id,
                    'url' => $listing['url'],
                ],
                [
                    'external_id' => $listing['external_id'] ?? null,
                    'batch_id' => (string) $batchId,
                    'status' => DiscoveredListingStatus::Pending,
                    'priority' => 0,
                ]
            );
        }
    }
}
