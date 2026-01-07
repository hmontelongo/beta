<?php

namespace App\Jobs\Concerns;

use App\Enums\ScrapeRunStatus;
use App\Models\ScrapeRun;

trait ChecksRunStatus
{
    protected function isRunActive(?int $scrapeRunId): bool
    {
        if (! $scrapeRunId) {
            return true;
        }

        $run = ScrapeRun::find($scrapeRunId);

        if (! $run) {
            return false;
        }

        return in_array($run->status, [
            ScrapeRunStatus::Discovering,
            ScrapeRunStatus::Scraping,
        ]);
    }
}
