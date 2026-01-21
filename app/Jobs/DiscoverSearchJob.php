<?php

namespace App\Jobs;

use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Events\DiscoveryCompleted;
use App\Jobs\Concerns\ChecksRunStatus;
use App\Jobs\Concerns\StoresDiscoveredListings;
use App\Models\Platform;
use App\Models\ScrapeJob;
use App\Models\ScrapeRun;
use App\Services\ScrapeOrchestrator;
use App\Services\ScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverSearchJob implements ShouldQueue
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
                'current_page' => 1,
            ]);

            $this->storeListings($platform->id, $result['listings'], $scrapeJob->id);

            // Get visible pages from pagination UI (excluding page 1 which we just scraped)
            $visiblePages = array_filter($result['visible_pages'] ?? [], fn ($p) => $p > 1);

            if ($scrapeRun) {
                // Start scraping page 1 listings immediately
                $orchestrator->dispatchScrapeBatch($scrapeRun->fresh());
            }

            if (! empty($visiblePages)) {
                $maxVisiblePage = max($visiblePages);

                // Dispatch jobs for ALL visible pages in parallel
                foreach ($visiblePages as $page) {
                    $isScout = ($page === $maxVisiblePage);
                    DiscoverPageJob::dispatch(
                        $scrapeJob->id,
                        $this->searchUrl,
                        $page,
                        $this->scrapeRunId,
                        $isScout
                    );
                }

                Log::info('Dispatched discovery jobs for visible pages', [
                    'pages' => array_values($visiblePages),
                    'scout_page' => $maxVisiblePage,
                ]);
            }

            $scrapeJob->update([
                'status' => ScrapeJobStatus::Completed,
                'completed_at' => now(),
                'result' => [
                    'listings_found' => count($result['listings']),
                    'visible_pages' => array_values($visiblePages),
                    'pages_dispatched' => count($visiblePages),
                ],
            ]);

            // If no more pages visible, discovery is complete
            if ($scrapeRun && empty($visiblePages)) {
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
     * Handle permanent job failure after all retries exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('DiscoverSearchJob failed permanently', [
            'platform_id' => $this->platformId,
            'search_url' => $this->searchUrl,
            'scrape_run_id' => $this->scrapeRunId,
            'error' => $exception?->getMessage(),
        ]);

        // Mark the scrape run as failed
        if ($this->scrapeRunId) {
            $scrapeRun = ScrapeRun::find($this->scrapeRunId);
            if ($scrapeRun) {
                app(ScrapeOrchestrator::class)->markFailed(
                    $scrapeRun,
                    $exception?->getMessage() ?? 'Discovery search failed'
                );
            }
        }
    }
}
