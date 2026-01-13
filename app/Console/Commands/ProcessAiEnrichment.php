<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Services\AI\ListingEnrichmentService;
use Illuminate\Console\Command;

class ProcessAiEnrichment extends Command
{
    protected $signature = 'ai:enrich
        {--listing= : Process a specific listing by ID}
        {--limit=10 : Number of listings to process}
        {--sync : Process synchronously instead of queueing}';

    protected $description = 'Process AI enrichment for pending listings';

    public function handle(ListingEnrichmentService $enrichmentService): int
    {
        $listingId = $this->option('listing');
        $limit = (int) $this->option('limit');
        $sync = $this->option('sync');

        if (! config('services.enrichment.enabled', true)) {
            $this->warn('AI enrichment is disabled. Set AI_ENRICHMENT_ENABLED=true to enable.');

            return Command::FAILURE;
        }

        if ($listingId) {
            return $this->processSingleListing($enrichmentService, (int) $listingId);
        }

        return $this->processBatch($enrichmentService, $limit, $sync);
    }

    protected function processSingleListing(ListingEnrichmentService $enrichmentService, int $listingId): int
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

        $enrichment = $enrichmentService->enrichListing($listing);

        if ($enrichment->status->isCompleted()) {
            $this->info("Enrichment completed. Quality score: {$enrichment->quality_score}");

            return Command::SUCCESS;
        }

        $this->error("Enrichment failed: {$enrichment->error_message}");

        return Command::FAILURE;
    }

    protected function processBatch(ListingEnrichmentService $enrichmentService, int $limit, bool $sync): int
    {
        $listings = Listing::pendingAiEnrichment()
            ->whereNotNull('raw_data')
            ->limit($limit)
            ->get();

        if ($listings->isEmpty()) {
            $this->info('No listings pending AI enrichment.');

            return Command::SUCCESS;
        }

        $this->info("Processing {$listings->count()} listings...");

        $processed = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($listings->count());
        $bar->start();

        foreach ($listings as $listing) {
            try {
                $enrichment = $enrichmentService->enrichListing($listing);

                if ($enrichment->status->isCompleted()) {
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $this->error("Listing #{$listing->id}: {$e->getMessage()}");
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Completed: {$processed} processed, {$failed} failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
