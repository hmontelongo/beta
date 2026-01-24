<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Prepares property data for rich display in collection views (PDF and public web).
 * Uses Property model accessors for computed properties.
 */
class CollectionPropertyPresenter
{
    /**
     * Prepare a collection of properties for rich display.
     *
     * @param  Collection<int, Property>  $properties
     * @param  bool  $embedImages  Convert external image URLs to base64 data URIs (for PDF)
     * @return Collection<int, array>
     */
    public function prepareProperties(Collection $properties, bool $embedImages = false): Collection
    {
        return $properties->map(fn (Property $property, int $index) => $this->prepareProperty($property, $index + 1, $embedImages));
    }

    /**
     * Prepare a single property for rich display.
     *
     * @param  bool  $embedImages  Convert external image URLs to base64 data URIs (for PDF)
     * @return array<string, mixed>
     */
    public function prepareProperty(Property $property, int $position, bool $embedImages = false): array
    {
        $extractedData = $property->ai_extracted_data ?? [];

        // Get up to 11 images: 5 for hero section + 6 for gallery
        $allImages = array_slice($property->images, 0, 11);

        if ($embedImages && count($allImages) > 0) {
            $allImages = $this->convertImagesToBase64($allImages);
        }

        // Split into hero images (first 5) and gallery images (remaining)
        $heroImages = array_slice($allImages, 0, 5);
        $galleryImages = array_slice($allImages, 5, 6);

        return [
            'position' => $position,
            'id' => $property->id,
            'property' => $property,

            // Hero images (1 main + 4 thumbnails)
            'images' => $heroImages,
            'heroImages' => $heroImages,

            // Gallery images (up to 6 for 2x3 grid)
            'galleryImages' => $galleryImages,

            // Price info - uses Property model accessors
            'price' => $property->primary_price,
            'pricePerM2' => $property->price_per_m2,

            // Basic specs
            'propertyType' => $property->property_type,
            'bedrooms' => $property->bedrooms,
            'bathrooms' => $property->bathrooms,
            'halfBathrooms' => $property->half_bathrooms,
            'parkingSpaces' => $property->parking_spots,
            'builtSizeM2' => $property->built_size_m2,
            'lotSizeM2' => $property->lot_size_m2,
            'ageYears' => $property->age_years,

            // Location
            'colonia' => $property->colonia,
            'city' => $property->city,
            'state' => $property->state,

            // Description - uses Property model accessor
            'description' => $property->description_text,

            // Rich data
            'categorizedAmenities' => $extractedData['amenities_categorized'] ?? null,
            'flatAmenities' => $property->amenities ?? [],
            'topAmenities' => $property->top_amenities,
            'rentalTerms' => $extractedData['terms'] ?? null,
            'buildingInfo' => $extractedData['location'] ?? null,
            'propertyInsights' => $extractedData['inferred'] ?? null,
            'pricingDetails' => $extractedData['pricing'] ?? null,

            // Additional location details - uses Property model accessor
            'latitude' => $property->latitude,
            'longitude' => $property->longitude,
            'fullAddress' => $property->full_address,

            // Maintenance fee - uses Property model accessor
            'maintenanceFee' => $property->maintenance_fee,
        ];
    }

    /**
     * Convert image URLs to base64 data URIs for reliable PDF rendering.
     *
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function convertImagesToBase64(array $urls): array
    {
        return collect($urls)
            ->map(function (string $url) {
                try {
                    $response = Http::timeout(5)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                            'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
                            'Referer' => parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).'/',
                        ])
                        ->get($url);

                    if ($response->successful()) {
                        $contentType = $response->header('Content-Type') ?? 'image/jpeg';
                        // Clean content type (remove charset if present)
                        $contentType = explode(';', $contentType)[0];
                        $base64 = base64_encode($response->body());

                        return "data:{$contentType};base64,{$base64}";
                    }

                    Log::warning('Failed to fetch image for PDF', ['url' => $url, 'status' => $response->status()]);
                } catch (\Exception $e) {
                    Log::warning('Exception fetching image for PDF', ['url' => $url, 'error' => $e->getMessage()]);
                }

                return null;
            })
            ->filter()
            ->values()
            ->toArray();
    }
}
