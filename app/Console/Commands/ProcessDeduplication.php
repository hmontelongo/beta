<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Services\Dedup\DeduplicationService;
use Illuminate\Console\Command;

class ProcessDeduplication extends Command
{
    protected $signature = 'dedup:process
        {--listing= : Process a specific listing by ID}
        {--limit=10 : Number of listings to process}';

    protected $description = 'Process deduplication for pending listings';

    public function handle(DeduplicationService $dedupService): int
    {
        $listingId = $this->option('listing');
        $limit = (int) $this->option('limit');

        if (! config('services.dedup.enabled', true)) {
            $this->warn('Deduplication is disabled. Set DEDUP_ENABLED=true to enable.');

            return Command::FAILURE;
        }

        if ($listingId) {
            return $this->processSingleListing($dedupService, (int) $listingId);
        }

        return $this->processBatch($dedupService, $limit);
    }

    protected function processSingleListing(DeduplicationService $dedupService, int $listingId): int
    {
        $listing = Listing::find($listingId);

        if (! $listing) {
            $this->error("Listing #{$listingId} not found.");

            return Command::FAILURE;
        }

        if (! $listing->raw_data) {
            $this->error("Listing #{$listingId} has no raw data to process.");

            return Command::FAILURE;
        }

        $this->info("Processing listing #{$listingId}...");

        $dedupService->processListing($listing);

        $listing->refresh();
        $this->info("Dedup status: {$listing->dedup_status->value}");

        if ($listing->property_id) {
            $this->info("Linked to property #{$listing->property_id}");
        }

        return Command::SUCCESS;
    }

    protected function processBatch(DeduplicationService $dedupService, int $limit): int
    {
        $listings = Listing::pendingDedup()
            ->whereNotNull('raw_data')
            ->limit($limit)
            ->get();

        if ($listings->isEmpty()) {
            $this->info('No listings pending deduplication.');

            return Command::SUCCESS;
        }

        $this->info("Processing {$listings->count()} listings...");

        $processed = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($listings->count());
        $bar->start();

        foreach ($listings as $listing) {
            try {
                $dedupService->processListing($listing);
                $processed++;
            } catch (\Throwable $e) {
                $this->error("Listing #{$listing->id}: {$e->getMessage()}");
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display stats
        $stats = $dedupService->getStats();
        $this->table(
            ['Status', 'Count'],
            [
                ['Pending', $stats['pending']],
                ['Matched', $stats['matched']],
                ['New', $stats['new']],
                ['Needs Review', $stats['needs_review']],
            ]
        );

        $this->info("Completed: {$processed} processed, {$failed} failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
