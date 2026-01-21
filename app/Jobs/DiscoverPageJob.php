<?php

namespace App\Jobs;

use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Events\DiscoveryCompleted;
use App\Jobs\Concerns\ChecksRunStatus;
use App\Jobs\Concerns\StoresDiscoveredListings;
use App\Models\ScrapeJob;
use App\Models\ScrapeRun;
use App\Services\ScrapeOrchestrator;
use App\Services\ScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverPageJob implements ShouldQueue
{
    use ChecksRunStatus, Queueable, StoresDiscoveredListings;

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
        public int $parentJobId,
        public string $searchUrl,
        public int $pageNumber,
        public ?int $scrapeRunId = null,
        public bool $isScout = false,
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
                    'is_scout' => $this->isScout,
                ],
            ]);

            if ($scrapeRun) {
                // Scout responsibility: check for pages beyond what we've already dispatched
                if ($this->isScout) {
                    $visiblePages = $result['visible_pages'] ?? [];
                    $newPages = array_filter($visiblePages, fn ($p) => $p > $this->pageNumber);

                    if (! empty($newPages)) {
                        $maxNewPage = max($newPages);
                        foreach ($newPages as $page) {
                            $isNewScout = ($page === $maxNewPage);
                            self::dispatch(
                                $this->parentJobId,
                                $this->searchUrl,
                                $page,
                                $this->scrapeRunId,
                                $isNewScout
                            );
                        }

                        Log::info('Scout discovered new pages', [
                            'scout_page' => $this->pageNumber,
                            'new_pages' => array_values($newPages),
                            'new_scout' => $maxNewPage,
                        ]);
                    }
                }

                // Dispatch scrape jobs for newly discovered listings immediately
                $scrapeRun = $scrapeRun->fresh();
                $orchestrator->dispatchScrapeBatch($scrapeRun);

                if ($orchestrator->checkDiscoveryComplete($scrapeRun)) {
                    DiscoveryCompleted::dispatch($scrapeRun);
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

            // Stats are now computed from actual records - no incrementStat needed

            throw $e;
        }
    }

    /**
     * Handle permanent job failure after all retries exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('DiscoverPageJob failed permanently', [
            'parent_job_id' => $this->parentJobId,
            'page' => $this->pageNumber,
            'scrape_run_id' => $this->scrapeRunId,
            'error' => $exception?->getMessage(),
        ]);

        // Stats are computed from actual records (ScrapeJob.status = Failed)
        // No manual incrementStat needed - the failed ScrapeJob record is the source of truth
    }
}
