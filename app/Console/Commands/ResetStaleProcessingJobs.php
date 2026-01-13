<?php

namespace App\Console\Commands;

use App\Enums\AiEnrichmentStatus;
use App\Enums\DedupStatus;
use App\Models\Listing;
use Illuminate\Console\Command;

class ResetStaleProcessingJobs extends Command
{
    protected $signature = 'listings:reset-stale {--minutes=5 : Minutes after which processing jobs are considered stale}';

    protected $description = 'Reset listings stuck in processing status back to pending';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $staleThreshold = now()->subMinutes($minutes);

        // Reset stale AI enrichment jobs
        $aiCount = Listing::where('ai_status', AiEnrichmentStatus::Processing)
            ->where('updated_at', '<', $staleThreshold)
            ->update(['ai_status' => AiEnrichmentStatus::Pending]);

        if ($aiCount > 0) {
            $this->info("Reset {$aiCount} stale AI enrichment jobs to pending.");
        }

        // Reset stale dedup jobs
        $dedupCount = Listing::where('dedup_status', DedupStatus::Processing)
            ->where('updated_at', '<', $staleThreshold)
            ->update(['dedup_status' => DedupStatus::Pending]);

        if ($dedupCount > 0) {
            $this->info("Reset {$dedupCount} stale dedup jobs to pending.");
        }

        if ($aiCount === 0 && $dedupCount === 0) {
            $this->info('No stale processing jobs found.');
        }

        return self::SUCCESS;
    }
}
