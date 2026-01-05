<?php

namespace App\Console\Commands;

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Models\DiscoveredListing;
use App\Models\Listing;
use App\Models\ScrapeJob;
use Illuminate\Console\Command;

class ScrapeStatusCommand extends Command
{
    protected $signature = 'scrape:status';

    protected $description = 'Show scraping status counts';

    public function handle(): int
    {
        $this->info('Discovered Listings:');
        $this->table(
            ['Status', 'Count'],
            collect(DiscoveredListingStatus::cases())->map(fn ($status) => [
                $status->value,
                DiscoveredListing::where('status', $status)->count(),
            ])->toArray()
        );

        $this->newLine();
        $this->info('Listings: '.Listing::count().' total');

        $this->newLine();
        $this->info('Scrape Jobs:');
        $this->table(
            ['Status', 'Count'],
            collect(ScrapeJobStatus::cases())->map(fn ($status) => [
                $status->value,
                ScrapeJob::where('status', $status)->count(),
            ])->toArray()
        );

        return Command::SUCCESS;
    }
}
