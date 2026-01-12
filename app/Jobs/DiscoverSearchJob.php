<?php

namespace App\Jobs;

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Events\DiscoveryCompleted;
use App\Jobs\Concerns\ChecksRunStatus;
use App\Models\DiscoveredListing;
use App\Models\Platform;
use App\Models\ScrapeJob;
use App\Models\ScrapeRun;
use App\Services\ScrapeOrchestrator;
use App\Services\ScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscoverSearchJob implements ShouldQueue
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
        public int $platformId,
        public string $searchUrl,
        public ?int $scrapeRunId = null,
    ) {
        $this->onQueue('discovery');
    }

    public function handle(ScraperService $scraperService, ScrapeOrchestrator $orchestrator): void
    {
        if (! $this->isRunActive($this->scrapeRunId)) {
            return;
        }

        $platform = Platform::findOrFail($this->platformId);
        $scrapeRun = $this->scrapeRunId ? ScrapeRun::find($this->scrapeRunId) : null;

        $scrapeJob = ScrapeJob::create([
            'platform_id' => $this->platformId,
            'scrape_run_id' => $this->scrapeRunId,
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

            if ($scrapeRun) {
                $orchestrator->updateStats($scrapeRun, [
                    'pages_total' => $result['total_pages'],
                    'pages_done' => 1,
                    'listings_found' => count($result['listings']),
                ]);

                // Start scraping page 1 listings immediately
                $orchestrator->dispatchScrapeBatch($scrapeRun->fresh());
            }

            for ($page = 2; $page <= $result['total_pages']; $page++) {
                DiscoverPageJob::dispatch($scrapeJob->id, $this->searchUrl, $page, $this->scrapeRunId);
            }

            $scrapeJob->update([
                'status' => ScrapeJobStatus::Completed,
                'completed_at' => now(),
                'result' => [
                    'page_1_listings' => count($result['listings']),
                    'pages_dispatched' => max(0, $result['total_pages'] - 1),
                ],
            ]);

            if ($scrapeRun && $result['total_pages'] <= 1) {
                DiscoveryCompleted::dispatch($scrapeRun);
            }
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

            if ($scrapeRun) {
                $orchestrator->markFailed($scrapeRun, $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * @param  array<array{url: string, external_id: string|null, preview?: array}>  $listings
     */
    protected function storeListings(Platform $platform, array $listings, int $batchId): void
    {
        foreach ($listings as $listing) {
            $preview = $listing['preview'] ?? [];

            DiscoveredListing::firstOrCreate(
                [
                    'platform_id' => $platform->id,
                    'url' => $listing['url'],
                ],
                [
                    'external_id' => $listing['external_id'] ?? null,
                    'batch_id' => (string) $batchId,
                    'scrape_run_id' => $this->scrapeRunId,
                    'status' => DiscoveredListingStatus::Pending,
                    'priority' => 0,
                    'preview_title' => $preview['title'] ?? null,
                    'preview_price' => $preview['price'] ?? null,
                    'preview_location' => $preview['location'] ?? null,
                    'preview_image' => $preview['image'] ?? null,
                ]
            );
        }
    }
}
