<?php

namespace App\Events;

use App\Models\ScrapeRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscoveryCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ScrapeRun $run
    ) {}
}
