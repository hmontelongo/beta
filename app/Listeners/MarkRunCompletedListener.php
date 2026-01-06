<?php

namespace App\Listeners;

use App\Events\ScrapingCompleted;
use App\Services\ScrapeOrchestrator;

class MarkRunCompletedListener
{
    public function __construct(
        protected ScrapeOrchestrator $orchestrator
    ) {}

    public function handle(ScrapingCompleted $event): void
    {
        $this->orchestrator->markCompleted($event->run);
    }
}
