<?php

namespace App\Console\Commands;

use App\Enums\DiscoveredListingStatus;
use App\Jobs\ScrapeListingJob;
use App\Models\DiscoveredListing;
use App\Models\Platform;
use Illuminate\Console\Command;

class ProcessDiscoveredCommand extends Command
{
    protected $signature = 'scrape:process {--platform=} {--limit=10}';

    protected $description = 'Process pending discovered listings';

    public function handle(): int
    {
        $platformName = $this->option('platform');
        $limit = (int) $this->option('limit');

        $query = DiscoveredListing::where('status', DiscoveredListingStatus::Pending)
            ->orderByDesc('priority')
            ->orderBy('created_at');

        if ($platformName) {
            $platform = Platform::where('name', $platformName)->first();

            if (! $platform) {
                $this->error("Platform '{$platformName}' not found.");

                return Command::FAILURE;
            }

            $query->where('platform_id', $platform->id);
        }

        $listings = $query->limit($limit)->get();

        foreach ($listings as $listing) {
            ScrapeListingJob::dispatch($listing->id);
        }

        $this->info("Dispatched {$listings->count()} listings for scraping.");

        return Command::SUCCESS;
    }
}
