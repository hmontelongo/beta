<?php

namespace App\Jobs;

use App\Models\Listing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessGeocodingBatchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(
        public ?int $batchSize = null,
    ) {
        $this->onQueue('geocoding');
    }

    public function handle(): void
    {
        if (! config('services.geocoding.enabled', true)) {
            Log::info('Geocoding is disabled, skipping batch job');

            return;
        }

        $batchSize = $this->batchSize ?? config('services.geocoding.batch_size', 50);

        $listings = Listing::pendingGeocoding()
            ->orderBy('created_at')
            ->limit($batchSize)
            ->get();

        foreach ($listings as $listing) {
            GeocodeListingJob::dispatch($listing->id);
        }

        if ($listings->isNotEmpty()) {
            Log::info('Geocoding batch dispatched', ['count' => $listings->count()]);
        }
    }
}
