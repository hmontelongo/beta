<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\Google\GeocodingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GeocodePropertyJob implements ShouldQueue
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
        public int $propertyId,
    ) {
        $this->onQueue('geocoding');
    }

    public function handle(GeocodingService $geocodingService): void
    {
        $property = Property::find($this->propertyId);

        if (! $property) {
            Log::warning('GeocodePropertyJob: Property not found', ['property_id' => $this->propertyId]);

            return;
        }

        // Skip if already geocoded
        if ($property->latitude && $property->longitude) {
            Log::debug('GeocodePropertyJob: Already geocoded', ['property_id' => $this->propertyId]);

            return;
        }

        $address = $property->address;
        $colonia = $property->colonia;
        $city = $property->city;
        $state = $property->state ?? 'Jalisco';

        // Build search query - use colonia if no specific address
        $searchAddress = $address ?: $colonia;

        if (! $searchAddress && ! $city) {
            Log::debug('GeocodePropertyJob: Skipped - no address or colonia', ['property_id' => $this->propertyId]);

            return;
        }

        // Build full address with colonia for better accuracy
        $fullAddress = collect([$address, $colonia])->filter()->implode(', ');

        $result = $geocodingService->geocode($fullAddress ?: $colonia, $city, $state);

        if ($result) {
            $updateData = [
                'latitude' => $result['lat'],
                'longitude' => $result['lng'],
            ];

            // Update colonia/city/state/postal_code if we got better data from geocoding
            if ($result['colonia'] && ! $property->colonia) {
                $updateData['colonia'] = $result['colonia'];
            }
            if ($result['city'] && ! $property->city) {
                $updateData['city'] = $result['city'];
            }
            if ($result['state'] && ! $property->state) {
                $updateData['state'] = $result['state'];
            }
            if ($result['postal_code'] && ! $property->postal_code) {
                $updateData['postal_code'] = $result['postal_code'];
            }

            $property->update($updateData);

            Log::info('GeocodePropertyJob: Success', [
                'property_id' => $this->propertyId,
                'latitude' => $result['lat'],
                'longitude' => $result['lng'],
                'geocoded_colonia' => $result['colonia'],
            ]);
        } else {
            Log::warning('GeocodePropertyJob: Failed to geocode', [
                'property_id' => $this->propertyId,
                'full_address' => $fullAddress,
                'city' => $city,
                'state' => $state,
            ]);
        }
    }
}
