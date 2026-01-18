<?php

namespace App\Console\Commands;

use App\Models\SearchQuery;
use App\Services\ScrapeOrchestrator;
use Illuminate\Console\Command;

class RunScheduledScrapes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:run-scheduled
                            {--force : Run all enabled scheduled queries regardless of next_run_at}
                            {--query= : Run a specific query by ID (bypasses schedule check)}
                            {--dry-run : Show what would run without actually running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled scrape queries that are due';

    /**
     * Execute the console command.
     */
    public function handle(ScrapeOrchestrator $orchestrator): int
    {
        // Single query mode - for testing specific queries
        if ($queryId = $this->option('query')) {
            return $this->runSingleQuery($orchestrator, (int) $queryId);
        }

        // Find queries due for running
        $queries = $this->option('force')
            ? SearchQuery::query()
                ->where('is_active', true)
                ->where('auto_enabled', true)
                ->get()
            : SearchQuery::dueForRun()->get();

        if ($queries->isEmpty()) {
            $this->info('No scheduled queries are due to run.');

            return self::SUCCESS;
        }

        $this->info("Found {$queries->count()} scheduled queries to run.");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Name', 'Platform', 'Frequency', 'Next Run'],
                $queries->map(fn ($q) => [
                    $q->id,
                    $q->name,
                    $q->platform->name,
                    $q->run_frequency->label(),
                    $q->next_run_at?->diffForHumans() ?? 'Not set',
                ])
            );

            return self::SUCCESS;
        }

        $started = 0;
        $skipped = 0;

        foreach ($queries as $query) {
            // Skip if there's already an active run
            if ($query->hasActiveRun()) {
                $this->warn("Skipping '{$query->name}' - already has an active run.");
                $skipped++;

                continue;
            }

            $this->line("Starting scrape for '{$query->name}' ({$query->platform->name})...");

            try {
                $run = $orchestrator->startRun($query);
                $query->scheduleNextRun();

                $this->info("  Started run #{$run->id}");
                $started++;
            } catch (\Exception $e) {
                $this->error("  Failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Completed: {$started} started, {$skipped} skipped.");

        return self::SUCCESS;
    }

    protected function runSingleQuery(ScrapeOrchestrator $orchestrator, int $queryId): int
    {
        $query = SearchQuery::find($queryId);

        if (! $query) {
            $this->error("Query #{$queryId} not found.");

            return self::FAILURE;
        }

        if (! $query->is_active) {
            $this->error("Query '{$query->name}' is not active.");

            return self::FAILURE;
        }

        if ($query->hasActiveRun()) {
            $this->error("Query '{$query->name}' already has an active run.");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("Would start scrape for '{$query->name}' ({$query->platform->name})");

            return self::SUCCESS;
        }

        $this->info("Starting scrape for '{$query->name}' ({$query->platform->name})...");

        $run = $orchestrator->startRun($query);

        // Only update schedule if auto_enabled
        if ($query->auto_enabled) {
            $query->scheduleNextRun();
        }

        $this->info("Started run #{$run->id}");

        return self::SUCCESS;
    }
}
