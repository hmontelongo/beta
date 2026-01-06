<?php

namespace App\Jobs;

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Events\DiscoveryCompleted;
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
    use Queueable;

    public int $timeout = 180; // 3 minutes for browser scraping

    public int $tries = 3;

    public function __construct(
        public int $parentJobId,
        public string $searchUrl,
        public int $pageNumber,
        public ?int $scrapeRunId = null,
    ) {}

    public function handle(ScraperService $scraperService, ScrapeOrchestrator $orchestrator): void
    {
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
                $stats = $scrapeRun->fresh()->stats ?? [];
                $orchestrator->updateStats($scrapeRun, [
                    'pages_done' => ($stats['pages_done'] ?? 0) + 1,
                    'listings_found' => ($stats['listings_found'] ?? 0) + count($result['listings']),
                ]);

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
                $stats = $scrapeRun->fresh()->stats ?? [];
                $orchestrator->updateStats($scrapeRun, [
                    'pages_failed' => ($stats['pages_failed'] ?? 0) + 1,
                ]);
            }

            throw $e;
        }
    }

    /**
     * @param  array<array{url: string, external_id: string|null}>  $listings
     */
    protected function storeListings(int $platformId, array $listings, int $batchId): void
    {
        foreach ($listings as $listing) {
            DiscoveredListing::firstOrCreate(
                [
                    'platform_id' => $platformId,
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
