<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\Google\GeocodingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GeocodeListingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public int $listingId,
    ) {
        $this->onQueue('geocoding');
    }

    public function handle(GeocodingService $geocodingService): void
    {
        $listing = Listing::find($this->listingId);

        if (! $listing) {
            Log::warning('GeocodeListingJob: Listing not found', ['listing_id' => $this->listingId]);

            return;
        }

        if ($listing->geocode_status === 'success') {
            return;
        }

        $rawData = $listing->raw_data ?? [];
        $address = $rawData['address'] ?? null;
        $city = $rawData['city'] ?? null;
        $state = $rawData['state'] ?? null;

        if (! $address && ! $city) {
            $listing->update([
                'geocode_status' => 'skipped',
                'geocoded_at' => now(),
            ]);

            Log::debug('GeocodeListingJob: Skipped - no address or city', ['listing_id' => $this->listingId]);

            return;
        }

        $result = $geocodingService->geocode($address ?? '', $city, $state);

        if ($result) {
            $listing->update([
                'latitude' => $result['lat'],
                'longitude' => $result['lng'],
                'geocode_status' => 'success',
                'geocoded_at' => now(),
            ]);

            Log::info('GeocodeListingJob: Success', [
                'listing_id' => $this->listingId,
                'latitude' => $result['lat'],
                'longitude' => $result['lng'],
            ]);
        } else {
            $listing->update([
                'geocode_status' => 'failed',
                'geocoded_at' => now(),
            ]);

            Log::warning('GeocodeListingJob: Failed to geocode', [
                'listing_id' => $this->listingId,
                'address' => $address,
                'city' => $city,
            ]);
        }
    }
}
