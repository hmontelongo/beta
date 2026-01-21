<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    protected string $apiKey;

    protected bool $enabled;

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key', '');
        $this->enabled = config('services.google.geocoding_enabled', true);
    }

    /**
     * Geocode an address to get coordinates and structured address components.
     *
     * @return array{lat: float, lng: float, formatted_address: string, place_id: string, colonia: ?string, city: ?string, state: ?string, postal_code: ?string}|null
     */
    public function geocode(string $address, ?string $city = null, ?string $state = null, string $country = 'Mexico'): ?array
    {
        if (! $this->enabled || empty($this->apiKey)) {
            Log::debug('Geocoding disabled or no API key');

            return null;
        }

        $fullAddress = $this->buildFullAddress($address, $city, $state, $country);

        try {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $fullAddress,
                'key' => $this->apiKey,
                'region' => 'mx',
                'language' => 'es',
            ]);

            if (! $response->successful()) {
                Log::warning('Geocoding request failed', [
                    'address' => $fullAddress,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            if ($data['status'] !== 'OK' || empty($data['results'])) {
                Log::debug('Geocoding returned no results', [
                    'address' => $fullAddress,
                    'status' => $data['status'],
                ]);

                return null;
            }

            $result = $data['results'][0];
            $location = $result['geometry']['location'];
            $addressComponents = $this->parseAddressComponents($result['address_components'] ?? []);

            return [
                'lat' => $location['lat'],
                'lng' => $location['lng'],
                'formatted_address' => $result['formatted_address'],
                'place_id' => $result['place_id'],
                'location_type' => $result['geometry']['location_type'] ?? 'APPROXIMATE',
                'colonia' => $addressComponents['colonia'],
                'city' => $addressComponents['city'],
                'state' => $addressComponents['state'],
                'postal_code' => $addressComponents['postal_code'],
            ];
        } catch (\Throwable $e) {
            Log::error('Geocoding error', [
                'address' => $fullAddress,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse address components from Google Maps response.
     *
     * @param  array<array{types: array<string>, long_name: string, short_name: string}>  $components
     * @return array{colonia: ?string, city: ?string, state: ?string, postal_code: ?string}
     */
    protected function parseAddressComponents(array $components): array
    {
        $result = [
            'colonia' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
        ];

        foreach ($components as $component) {
            $types = $component['types'] ?? [];

            // Colonia: sublocality_level_1, neighborhood, or sublocality
            if (in_array('sublocality_level_1', $types) || in_array('neighborhood', $types)) {
                $result['colonia'] = $component['long_name'];
            } elseif (in_array('sublocality', $types) && $result['colonia'] === null) {
                $result['colonia'] = $component['long_name'];
            }

            // City: locality
            if (in_array('locality', $types)) {
                $result['city'] = $component['long_name'];
            }

            // State: administrative_area_level_1
            if (in_array('administrative_area_level_1', $types)) {
                $result['state'] = $component['long_name'];
            }

            // Postal code
            if (in_array('postal_code', $types)) {
                $result['postal_code'] = $component['long_name'];
            }
        }

        return $result;
    }

    /**
     * Generate a Google Maps static image URL.
     */
    public function getStaticMapUrl(float $lat, float $lng, int $width = 400, int $height = 200, int $zoom = 15): string
    {
        $params = http_build_query([
            'center' => "{$lat},{$lng}",
            'zoom' => $zoom,
            'size' => "{$width}x{$height}",
            'maptype' => 'roadmap',
            'markers' => "color:red|{$lat},{$lng}",
            'key' => $this->apiKey,
        ]);

        return "https://maps.googleapis.com/maps/api/staticmap?{$params}";
    }

    /**
     * Generate a Google Maps embed URL.
     */
    public function getEmbedUrl(float $lat, float $lng): string
    {
        return "https://www.google.com/maps/embed/v1/place?key={$this->apiKey}&q={$lat},{$lng}";
    }

    /**
     * Generate a Google Maps link URL.
     */
    public function getMapsUrl(float $lat, float $lng): string
    {
        return "https://www.google.com/maps?q={$lat},{$lng}";
    }

    /**
     * Build full address string for geocoding.
     */
    protected function buildFullAddress(string $address, ?string $city, ?string $state, string $country): string
    {
        $parts = array_filter([
            $address,
            $city,
            $state,
            $country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if coordinates are likely accurate based on location type.
     */
    public function isAccurateLocation(string $locationType): bool
    {
        return in_array($locationType, ['ROOFTOP', 'RANGE_INTERPOLATED']);
    }
}
