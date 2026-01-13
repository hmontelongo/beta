<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\AI\ListingEnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAiEnrichmentBatchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800; // 30 minutes for batch processing (with rate limiting delays)

    public int $tries = 1; // Don't retry - individual listings handle their own errors

    public function __construct(
        public ?int $batchSize = null,
    ) {
        $this->onQueue('ai-enrichment');
    }

    public function handle(ListingEnrichmentService $enrichmentService): void
    {
        $batchSize = $this->batchSize ?? config('services.enrichment.batch_size', 10);

        if (! config('services.enrichment.enabled', true)) {
            Log::info('AI enrichment is disabled, skipping batch job');

            return;
        }

        $listings = Listing::pendingAiEnrichment()
            ->whereNotNull('raw_data')
            ->limit($batchSize)
            ->get();

        if ($listings->isEmpty()) {
            Log::debug('No listings pending AI enrichment');

            return;
        }

        Log::info('Starting AI enrichment batch', [
            'count' => $listings->count(),
            'batch_size' => $batchSize,
        ]);

        $processed = 0;
        $failed = 0;

        // Rate limit: ~10k tokens/minute, each request ~3k tokens = ~3 requests/minute
        // Add 20 second delay between requests to stay safely under limit
        $delaySeconds = config('services.enrichment.delay_seconds', 20);

        foreach ($listings as $index => $listing) {
            // Add delay between requests (not before first one)
            if ($index > 0 && $delaySeconds > 0) {
                sleep($delaySeconds);
            }

            try {
                $enrichment = $enrichmentService->enrichListing($listing);

                if ($enrichment->status->isCompleted()) {
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                Log::error('AI enrichment failed for listing', [
                    'listing_id' => $listing->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;

                // If rate limited, wait longer before next attempt
                if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'rate_limit')) {
                    Log::warning('Rate limited, waiting 60 seconds before continuing');
                    sleep(60);
                }
            }
        }

        Log::info('AI enrichment batch completed', [
            'processed' => $processed,
            'failed' => $failed,
            'total' => $listings->count(),
        ]);
    }
}
