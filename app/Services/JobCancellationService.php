<?php

namespace App\Services;

use App\Enums\DedupStatus;
use App\Enums\DiscoveredListingStatus;
use App\Enums\ListingGroupStatus;
use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeRunStatus;
use App\Models\DiscoveredListing;
use App\Models\Listing;
use App\Models\ListingGroup;
use App\Models\ScrapeJob;
use App\Models\ScrapeRun;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class JobCancellationService
{
    /**
     * Cancel property creation jobs and reset listing groups to pending.
     *
     * @return array{cancelled: int, reset: int}
     */
    public function cancelPropertyCreationJobs(): array
    {
        $processingCount = ListingGroup::where('status', ListingGroupStatus::ProcessingAi)->count();

        // Clear the property creation queue
        $this->clearQueue('property-creation');

        // Reset all processing groups to pending AI
        $resetCount = ListingGroup::where('status', ListingGroupStatus::ProcessingAi)
            ->update(['status' => ListingGroupStatus::PendingAi]);

        Log::info('Property creation jobs cancelled', [
            'queue_cleared' => 'property-creation',
            'groups_reset' => $resetCount,
        ]);

        return [
            'cancelled' => $processingCount,
            'reset' => $resetCount,
        ];
    }

    /**
     * Cancel deduplication jobs and reset listings to pending.
     *
     * @return array{cancelled: int, reset: int}
     */
    public function cancelDeduplicationJobs(): array
    {
        $processingCount = Listing::where('dedup_status', DedupStatus::Processing)->count();

        // Clear the dedup queue
        $this->clearQueue('dedup');

        // Reset all processing listings to pending
        $resetCount = Listing::where('dedup_status', DedupStatus::Processing)
            ->update(['dedup_status' => DedupStatus::Pending]);

        Log::info('Deduplication jobs cancelled', [
            'queue_cleared' => 'dedup',
            'listings_reset' => $resetCount,
        ]);

        return [
            'cancelled' => $processingCount,
            'reset' => $resetCount,
        ];
    }

    /**
     * Cancel scraping jobs for a specific run.
     *
     * @return array{scrape_jobs_reset: int, discovered_listings_reset: int}
     */
    public function cancelScrapingJobs(?int $scrapeRunId = null): array
    {
        // Clear the scraping queue
        $this->clearQueue('scraping');

        $scrapeJobsReset = 0;
        $discoveredListingsReset = 0;

        if ($scrapeRunId) {
            // Reset scrape jobs for this run
            $scrapeJobsReset = ScrapeJob::where('scrape_run_id', $scrapeRunId)
                ->where('status', ScrapeJobStatus::Running)
                ->update([
                    'status' => ScrapeJobStatus::Failed,
                    'completed_at' => now(),
                ]);

            // Reset queued listings back to pending (their jobs were cleared from the queue)
            // This allows them to be resumed later
            $discoveredListingsReset = DiscoveredListing::where('scrape_run_id', $scrapeRunId)
                ->where('status', DiscoveredListingStatus::Queued)
                ->update(['status' => DiscoveredListingStatus::Pending]);

            // Update the scrape run status
            ScrapeRun::where('id', $scrapeRunId)
                ->whereIn('status', [
                    ScrapeRunStatus::Discovering,
                    ScrapeRunStatus::Scraping,
                ])
                ->update([
                    'status' => ScrapeRunStatus::Stopped,
                    'completed_at' => now(),
                ]);
        } else {
            // Reset all running scrape jobs
            $scrapeJobsReset = ScrapeJob::where('status', ScrapeJobStatus::Running)
                ->update([
                    'status' => ScrapeJobStatus::Failed,
                    'completed_at' => now(),
                ]);
        }

        Log::info('Scraping jobs cancelled', [
            'scrape_run_id' => $scrapeRunId,
            'scrape_jobs_reset' => $scrapeJobsReset,
            'discovered_listings_reset' => $discoveredListingsReset,
        ]);

        return [
            'scrape_jobs_reset' => $scrapeJobsReset,
            'discovered_listings_reset' => $discoveredListingsReset,
        ];
    }

    /**
     * Cancel discovery jobs and associated scraping.
     *
     * @return array{scrape_jobs_reset: int}
     */
    public function cancelDiscoveryJobs(?int $scrapeRunId = null): array
    {
        // Clear the discovery queue
        $this->clearQueue('discovery');

        $scrapeJobsReset = 0;

        if ($scrapeRunId) {
            $scrapeJobsReset = ScrapeJob::where('scrape_run_id', $scrapeRunId)
                ->where('status', ScrapeJobStatus::Running)
                ->update([
                    'status' => ScrapeJobStatus::Failed,
                    'completed_at' => now(),
                ]);

            ScrapeRun::where('id', $scrapeRunId)
                ->where('status', ScrapeRunStatus::Discovering)
                ->update([
                    'status' => ScrapeRunStatus::Stopped,
                    'completed_at' => now(),
                ]);
        }

        Log::info('Discovery jobs cancelled', [
            'scrape_run_id' => $scrapeRunId,
            'scrape_jobs_reset' => $scrapeJobsReset,
        ]);

        return [
            'scrape_jobs_reset' => $scrapeJobsReset,
        ];
    }

    /**
     * Clear all jobs from a specific queue.
     */
    protected function clearQueue(string $queueName): void
    {
        $connection = config('queue.default');

        if ($connection !== 'redis') {
            Log::warning('Queue clearing only supported for Redis', [
                'connection' => $connection,
                'queue' => $queueName,
            ]);

            return;
        }

        // Note: Redis facade already applies the prefix from config,
        // so we don't need to add it manually

        // Clear pending jobs
        Redis::del("queues:{$queueName}");

        // Clear reserved jobs (currently processing)
        Redis::del("queues:{$queueName}:reserved");

        // Clear delayed jobs
        Redis::del("queues:{$queueName}:delayed");

        // Clear notify key (used by Horizon)
        Redis::del("queues:{$queueName}:notify");

        Log::info('Queue cleared', ['queue' => $queueName]);
    }

    /**
     * Get queue statistics for monitoring.
     *
     * @return array<string, array{pending: int, reserved: int, delayed: int}>
     */
    public function getQueueStats(): array
    {
        $queues = ['property-creation', 'dedup', 'scraping', 'discovery'];
        $stats = [];

        $connection = config('queue.default');
        if ($connection !== 'redis') {
            return $stats;
        }

        // Note: Redis facade already applies the prefix from config,
        // so we don't need to add it manually
        foreach ($queues as $queue) {
            $stats[$queue] = [
                'pending' => (int) Redis::llen("queues:{$queue}"),
                'reserved' => (int) Redis::zcard("queues:{$queue}:reserved"),
                'delayed' => (int) Redis::zcard("queues:{$queue}:delayed"),
            ];
        }

        return $stats;
    }
}
