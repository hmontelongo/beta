<?php

namespace App\Services;

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapePhase;
use App\Enums\ScrapeRunStatus;
use App\Events\ScrapeRunProgress;
use App\Jobs\DiscoverSearchJob;
use App\Jobs\ScrapeListingJob;
use App\Models\DiscoveredListing;
use App\Models\ScrapeJob;
use App\Models\ScrapeRun;
use App\Models\SearchQuery;

class ScrapeOrchestrator
{
    // Maximum duration for a scrape run (30 minutes)
    public const MAX_RUN_DURATION_MINUTES = 30;

    public function startRun(SearchQuery $query): ScrapeRun
    {
        $run = ScrapeRun::create([
            'platform_id' => $query->platform_id,
            'search_query_id' => $query->id,
            'status' => ScrapeRunStatus::Discovering,
            'phase' => ScrapePhase::Discover,
            'started_at' => now(),
            'stats' => [
                'pages_total' => 0,
                'pages_done' => 0,
                'listings_found' => 0,
                'listings_scraped' => 0,
            ],
        ]);

        DiscoverSearchJob::dispatch(
            $run->platform_id,
            $query->search_url,
            $run->id
        );

        $query->update(['last_run_at' => now()]);

        return $run;
    }

    public function transitionToScraping(ScrapeRun $run): void
    {
        $run->update([
            'status' => ScrapeRunStatus::Scraping,
            'phase' => ScrapePhase::Scrape,
        ]);

        ScrapeRunProgress::dispatch($run, 'phase_changed', [
            'phase' => ScrapePhase::Scrape->value,
        ]);

        // Dispatch any remaining pending listings (batch scraping may have already queued some)
        $this->dispatchScrapeBatch($run);
    }

    public function markCompleted(ScrapeRun $run): void
    {
        $run->update([
            'status' => ScrapeRunStatus::Completed,
            'completed_at' => now(),
        ]);

        ScrapeRunProgress::dispatch($run, 'completed', [
            'stats' => $run->stats,
        ]);
    }

    public function markFailed(ScrapeRun $run, string $errorMessage): void
    {
        $run->update([
            'status' => ScrapeRunStatus::Failed,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);

        ScrapeRunProgress::dispatch($run, 'failed', [
            'error' => $errorMessage,
        ]);
    }

    public function updateStats(ScrapeRun $run, array $updates): void
    {
        $stats = $run->stats ?? [];
        $run->update([
            'stats' => array_merge($stats, $updates),
        ]);

        ScrapeRunProgress::dispatch($run, 'stats_updated', $updates);
    }

    /**
     * Atomically increment a stat value using database locking.
     * Use this for concurrent updates from multiple workers.
     */
    public function incrementStat(ScrapeRun $run, string $key, int $amount = 1): void
    {
        \DB::transaction(function () use ($run, $key, $amount) {
            $lockedRun = ScrapeRun::lockForUpdate()->find($run->id);
            $stats = $lockedRun->stats ?? [];
            $stats[$key] = ($stats[$key] ?? 0) + $amount;
            $lockedRun->update(['stats' => $stats]);
        });

        ScrapeRunProgress::dispatch($run->fresh(), 'stats_updated', [$key => $amount]);
    }

    public function checkDiscoveryComplete(ScrapeRun $run): bool
    {
        // Use computed stats from actual records - single source of truth
        $stats = $run->computeStats();
        $pagesTotal = $stats['pages_total'] ?? 0;
        $pagesDone = $stats['pages_done'] ?? 0;

        return $pagesTotal > 0 && $pagesDone >= $pagesTotal;
    }

    /**
     * Dispatch scrape jobs for pending listings (batch scraping during discovery).
     */
    public function dispatchScrapeBatch(ScrapeRun $run): int
    {
        // Get IDs of listings that already have running jobs to prevent duplicates
        $listingsWithRunningJobs = ScrapeJob::where('scrape_run_id', $run->id)
            ->where('status', ScrapeJobStatus::Running)
            ->pluck('discovered_listing_id');

        $pendingListingIds = DiscoveredListing::query()
            ->where('scrape_run_id', $run->id)
            ->where('status', DiscoveredListingStatus::Pending)
            ->whereNotIn('id', $listingsWithRunningJobs)
            ->pluck('id');

        if ($pendingListingIds->isEmpty()) {
            return 0;
        }

        // Bulk update status to Queued (single query instead of N)
        DiscoveredListing::whereIn('id', $pendingListingIds)
            ->update(['status' => DiscoveredListingStatus::Queued]);

        // Dispatch jobs
        foreach ($pendingListingIds as $listingId) {
            ScrapeListingJob::dispatch($listingId, $run->id);
        }

        return $pendingListingIds->count();
    }

    public function checkScrapingComplete(ScrapeRun $run): bool
    {
        // Use computed stats from actual records - single source of truth
        $stats = $run->computeStats();
        $listingsFound = $stats['listings_found'] ?? 0;
        $listingsScraped = $stats['listings_scraped'] ?? 0;
        $listingsFailed = $stats['listings_failed'] ?? 0;

        // Complete when all listings are either scraped or failed
        return $listingsFound > 0 && ($listingsScraped + $listingsFailed) >= $listingsFound;
    }

    public function isTimedOut(ScrapeRun $run): bool
    {
        if (! $run->started_at) {
            return false;
        }

        return $run->started_at->diffInMinutes(now()) > self::MAX_RUN_DURATION_MINUTES;
    }

    public function markTimedOut(ScrapeRun $run): void
    {
        $run->update([
            'status' => ScrapeRunStatus::Failed,
            'error_message' => 'Run timed out after '.self::MAX_RUN_DURATION_MINUTES.' minutes',
            'completed_at' => now(),
        ]);

        ScrapeRunProgress::dispatch($run, 'timed_out', [
            'duration_minutes' => $run->started_at->diffInMinutes(now()),
        ]);
    }

    public function cleanupStaleRuns(): int
    {
        $staleRuns = ScrapeRun::query()
            ->whereIn('status', [ScrapeRunStatus::Discovering, ScrapeRunStatus::Scraping])
            ->where('started_at', '<', now()->subMinutes(self::MAX_RUN_DURATION_MINUTES))
            ->get();

        foreach ($staleRuns as $run) {
            $this->markTimedOut($run);
        }

        return $staleRuns->count();
    }

    /**
     * Clean up stale scrape jobs that are stuck in "running" status.
     * Jobs older than the timeout period are marked as failed.
     */
    public function cleanupStaleJobs(ScrapeRun $run): int
    {
        return ScrapeJob::where('scrape_run_id', $run->id)
            ->where('status', ScrapeJobStatus::Running)
            ->where('started_at', '<', now()->subSeconds(180)) // Job timeout is 180s
            ->update([
                'status' => ScrapeJobStatus::Failed,
                'completed_at' => now(),
                'error_message' => 'Job timed out or worker crashed',
            ]);
    }

    /**
     * Resume a stopped/failed run by re-queuing pending, queued, and failed listings.
     * Queued listings need re-dispatching because their jobs were pruned when stopped.
     */
    public function resumeRun(ScrapeRun $run): int
    {
        // Clean up any stale "running" jobs before resuming to prevent duplicates
        $this->cleanupStaleJobs($run);

        // Get IDs of listings that already have running jobs
        $listingsWithRunningJobs = ScrapeJob::where('scrape_run_id', $run->id)
            ->where('status', ScrapeJobStatus::Running)
            ->pluck('discovered_listing_id');

        $listingsToResume = DiscoveredListing::query()
            ->where('scrape_run_id', $run->id)
            ->whereIn('status', [
                DiscoveredListingStatus::Pending,
                DiscoveredListingStatus::Queued,
                DiscoveredListingStatus::Failed,
            ])
            ->whereNotIn('id', $listingsWithRunningJobs)
            ->get();

        if ($listingsToResume->isEmpty()) {
            return 0;
        }

        $run->update([
            'status' => ScrapeRunStatus::Scraping,
            'phase' => ScrapePhase::Scrape,
            'error_message' => null,
            'completed_at' => null,
        ]);

        foreach ($listingsToResume as $listing) {
            $listing->update(['status' => DiscoveredListingStatus::Queued]);
            ScrapeListingJob::dispatch($listing->id, $run->id);
        }

        ScrapeRunProgress::dispatch($run, 'resumed', [
            'listings_queued' => $listingsToResume->count(),
        ]);

        return $listingsToResume->count();
    }
}
