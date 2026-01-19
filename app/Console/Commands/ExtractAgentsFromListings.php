<?php

namespace App\Console\Commands;

use App\Services\AgentExtractionService;
use Illuminate\Console\Command;

class ExtractAgentsFromListings extends Command
{
    protected $signature = 'listings:extract-agents
                            {--limit=1000 : Maximum number of listings to process}
                            {--all : Process all unlinked listings (ignore limit)}';

    protected $description = 'Extract and link agents/agencies from listing publisher data';

    protected const BATCH_SIZE = 100;

    public function handle(AgentExtractionService $service): int
    {
        $limit = $this->option('all') ? PHP_INT_MAX : (int) $this->option('limit');

        $this->components->info('Processing listings without agent/agency links...');

        $totalProcessed = 0;

        do {
            $processed = $service->processUnlinkedListings(self::BATCH_SIZE);
            $totalProcessed += $processed;

            if ($processed > 0) {
                $this->line("  Processed {$totalProcessed} listings...");
            }
        } while ($processed === self::BATCH_SIZE && $totalProcessed < $limit);

        $this->newLine();
        $this->components->info("Done! Total listings processed: {$totalProcessed}");

        return self::SUCCESS;
    }
}
