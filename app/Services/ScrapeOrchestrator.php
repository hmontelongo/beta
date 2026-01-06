<?php

namespace App\Services;

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapePhase;
use App\Enums\ScrapeRunStatus;
use App\Events\ScrapeRunProgress;
use App\Jobs\DiscoverSearchJob;
use App\Jobs\ScrapeListingJob;
use App\Models\DiscoveredListing;
use App\Models\ScrapeRun;
use App\Models\SearchQuery;

class ScrapeOrchestrator
{
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

        $pendingListings = DiscoveredListing::query()
            ->where('platform_id', $run->platform_id)
            ->where('status', DiscoveredListingStatus::Pending)
            ->get();

        foreach ($pendingListings as $listing) {
            ScrapeListingJob::dispatch($listing->id, $run->id);
        }
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

    public function checkDiscoveryComplete(ScrapeRun $run): bool
    {
        $stats = $run->stats ?? [];
        $pagesTotal = $stats['pages_total'] ?? 0;
        $pagesDone = $stats['pages_done'] ?? 0;

        return $pagesTotal > 0 && $pagesDone >= $pagesTotal;
    }

    public function checkScrapingComplete(ScrapeRun $run): bool
    {
        $stats = $run->stats ?? [];
        $listingsFound = $stats['listings_found'] ?? 0;
        $listingsScraped = $stats['listings_scraped'] ?? 0;

        return $listingsFound > 0 && $listingsScraped >= $listingsFound;
    }
}
