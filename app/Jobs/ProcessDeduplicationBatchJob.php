<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\Dedup\DeduplicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessDeduplicationBatchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600; // 10 minutes for batch processing

    public int $tries = 1;

    public function __construct(
        public ?int $batchSize = null,
    ) {
        $this->onQueue('dedup');
    }

    public function handle(DeduplicationService $dedupService): void
    {
        $batchSize = $this->batchSize ?? config('services.dedup.batch_size', 100);

        if (! config('services.dedup.enabled', true)) {
            Log::info('Deduplication is disabled, skipping batch job');

            return;
        }

        $listings = Listing::pendingDedup()
            ->whereNotNull('raw_data')
            ->limit($batchSize)
            ->get();

        if ($listings->isEmpty()) {
            Log::debug('No listings pending deduplication');

            return;
        }

        Log::info('Starting deduplication batch', [
            'count' => $listings->count(),
            'batch_size' => $batchSize,
        ]);

        $processed = 0;
        $failed = 0;

        foreach ($listings as $listing) {
            try {
                $dedupService->processListing($listing);
                $processed++;
            } catch (\Throwable $e) {
                Log::error('Deduplication failed for listing', [
                    'listing_id' => $listing->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        Log::info('Deduplication batch completed', [
            'processed' => $processed,
            'failed' => $failed,
            'total' => $listings->count(),
        ]);
    }
}
