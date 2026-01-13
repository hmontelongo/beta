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

    public int $timeout = 600; // 10 minutes for batch processing

    public int $tries = 1; // Don't retry - individual listings handle their own errors

    public function __construct(
        public ?int $batchSize = null,
    ) {
        $this->onQueue('ai-enrichment');
    }

    public function handle(ListingEnrichmentService $enrichmentService): void
    {
        $batchSize = $this->batchSize ?? config('services.enrichment.batch_size', 50);

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

        foreach ($listings as $listing) {
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
            }
        }

        Log::info('AI enrichment batch completed', [
            'processed' => $processed,
            'failed' => $failed,
            'total' => $listings->count(),
        ]);
    }
}
