<?php

namespace App\Listeners;

use App\Events\DiscoveryCompleted;
use App\Services\ScrapeOrchestrator;

class TransitionToScrapingListener
{
    public function __construct(
        protected ScrapeOrchestrator $orchestrator
    ) {}

    public function handle(DiscoveryCompleted $event): void
    {
        $this->orchestrator->transitionToScraping($event->run);
    }
}
