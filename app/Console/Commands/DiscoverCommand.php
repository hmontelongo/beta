<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverSearchJob;
use App\Models\Platform;
use Illuminate\Console\Command;

class DiscoverCommand extends Command
{
    protected $signature = 'scrape:discover {platform} {search_url}';

    protected $description = 'Start discovery for a platform search URL';

    public function handle(): int
    {
        $platformName = $this->argument('platform');
        $searchUrl = $this->argument('search_url');

        $platform = Platform::where('name', $platformName)->first();

        if (! $platform) {
            $this->error("Platform '{$platformName}' not found.");

            return Command::FAILURE;
        }

        DiscoverSearchJob::dispatch($platform->id, $searchUrl);

        $this->info("Discovery started for {$platform->name}, job dispatched.");

        return Command::SUCCESS;
    }
}
