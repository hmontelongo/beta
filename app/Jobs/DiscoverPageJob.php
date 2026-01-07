<?php

namespace App\Jobs;

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Events\DiscoveryCompleted;
use App\Jobs\Concerns\ChecksRunStatus;
use App\Models\DiscoveredListing;
use App\Models\ScrapeJob;
use App\Models\ScrapeRun;
use App\Services\ScrapeOrchestrator;
use App\Services\ScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscoverPageJob implements ShouldQueue
{
    use ChecksRunStatus, Queueable;

    public int $timeout = 180; // 3 minutes for browser scraping

    public int $tries = 3;

    public function __construct(
        public int $parentJobId,
        public string $searchUrl,
        public int $pageNumber,
        public ?int $scrapeRunId = null,
    ) {
        $this->onQueue('discovery');
    }

    public function handle(ScraperService $scraperService, ScrapeOrchestrator $orchestrator): void
    {
        if (! $this->isRunActive($this->scrapeRunId)) {
            return;
        }

        $parentJob = ScrapeJob::findOrFail($this->parentJobId);
        $scrapeRun = $this->scrapeRunId ? ScrapeRun::find($this->scrapeRunId) : null;

        $childJob = ScrapeJob::create([
            'platform_id' => $parentJob->platform_id,
            'scrape_run_id' => $this->scrapeRunId,
            'parent_id' => $this->parentJobId,
            'target_url' => $this->searchUrl,
            'job_type' => ScrapeJobType::Discovery,
            'current_page' => $this->pageNumber,
            'status' => ScrapeJobStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $result = $scraperService->discoverPage($this->searchUrl, $this->pageNumber);

            $this->storeListings($parentJob->platform_id, $result['listings'], $this->parentJobId);

            $childJob->update([
                'status' => ScrapeJobStatus::Completed,
                'completed_at' => now(),
                'result' => [
                    'listings_found' => count($result['listings']),
                ],
            ]);

            if ($scrapeRun) {
                $orchestrator->incrementStat($scrapeRun, 'pages_done');
                $orchestrator->incrementStat($scrapeRun, 'listings_found', count($result['listings']));

                // Dispatch scrape jobs for newly discovered listings immediately
                $orchestrator->dispatchScrapeBatch($scrapeRun->fresh());

                if ($orchestrator->checkDiscoveryComplete($scrapeRun->fresh())) {
                    DiscoveryCompleted::dispatch($scrapeRun->fresh());
                }
            }
        } catch (\Throwable $e) {
            Log::error('Discovery page failed', [
                'parent_job_id' => $this->parentJobId,
                'page' => $this->pageNumber,
                'error' => $e->getMessage(),
            ]);

            $childJob->update([
                'status' => ScrapeJobStatus::Failed,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            // Track failure in run stats
            if ($scrapeRun) {
                $orchestrator->incrementStat($scrapeRun, 'pages_failed');
            }

            throw $e;
        }
    }

    /**
     * @param  array<array{url: string, external_id: string|null, preview?: array}>  $listings
     */
    protected function storeListings(int $platformId, array $listings, int $batchId): void
    {
        foreach ($listings as $listing) {
            $preview = $listing['preview'] ?? [];

            DiscoveredListing::firstOrCreate(
                [
                    'platform_id' => $platformId,
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
