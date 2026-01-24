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
            'parkingSpaces' => $property->parking_spaces,
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

    /**
     * Humanize amenity name for display.
     */
    public static function humanizeAmenity(string $amenity): string
    {
        $key = 'amenities.'.strtolower($amenity);
        $translation = __($key);

        return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $amenity));
    }

    /**
     * Get emoji icon for an amenity.
     */
    public static function getAmenityIcon(string $amenity): string
    {
        return match (strtolower($amenity)) {
            // Unit amenities
            'integrated_kitchen', 'kitchen' => '🍳',
            'terrace', 'balcony' => '🌅',
            'laundry_room', 'washer', 'dryer' => '🧺',
            'closet', 'walk_in_closet' => '👔',
            'air_conditioning', 'ac' => '❄️',
            'heating' => '🔥',
            'dishwasher' => '🍽️',
            'furnished', 'semi_furnished' => '🛋️',
            'granite_countertops' => '💎',
            'natural_gas' => '🔥',

            // Building amenities
            'swimming_pool', 'pool' => '🏊',
            'gym' => '💪',
            'elevator' => '🛗',
            'playground' => '🎠',
            'party_room', 'multipurpose_room', 'meeting_room' => '🎉',
            'garden', 'roof_garden' => '🌳',
            'bbq_area', 'grill' => '🔥',
            'pet_area' => '🐕',
            'jacuzzi' => '🛁',
            'rooftop' => '🌆',
            'coworking', 'business_center' => '💼',
            'bike_parking' => '🚲',
            'common_area' => '🏠',
            'fountain' => '⛲',
            'convenience_store' => '🏪',
            'restaurant' => '🍽️',

            // Services
            'security', 'security_24h', '24_hour_security', 'guard_house', 'security_booth' => '🛡️',
            'concierge' => '🛎️',
            'covered_parking', 'visitor_parking' => '🅿️',
            'storage' => '📦',
            'gated_community' => '🚧',
            'security_cameras' => '📹',
            'maintenance' => '🔧',
            'cleaning' => '🧹',
            'valet_parking' => '🚗',
            'disabled_access', 'wheelchair_access', 'accessibility_features' => '♿',
            'package_reception' => '📬',

            // Extras
            'pet_friendly' => '🐾',
            'solar_panels' => '☀️',
            'water_tank' => '💧',
            'generator' => '⚡',

            default => '✓',
        };
    }

    /**
     * Get emoji icon for a landmark type.
     */
    public static function getLandmarkIcon(string $type): string
    {
        return match ($type) {
            'university' => '🎓',
            'school', 'education' => '🏫',
            'park', 'recreation' => '🌳',
            'shopping_mall', 'mall', 'shopping' => '🛒',
            'stadium' => '🏟️',
            'government' => '🏛️',
            'hospital', 'health', 'clinic' => '🏥',
            'metro', 'transport', 'bus' => '🚇',
            'restaurant', 'food' => '🍽️',
            'church', 'religious' => '⛪',
            'bank' => '🏦',
            'gym', 'fitness' => '💪',
            default => '📍',
        };
    }

    /**
     * Get Spanish label for property type.
     */
    public static function getPropertyTypeLabel(?\App\Enums\PropertyType $type): string
    {
        if (! $type) {
            return 'Propiedad';
        }

        return match ($type->value) {
            'apartment' => 'Departamento',
            'house' => 'Casa',
            'commercial' => 'Comercial',
            'land' => 'Terreno',
            'office' => 'Oficina',
            'warehouse' => 'Bodega',
            default => $type->value,
        };
    }

    /**
     * Get Spanish label for target audience.
     */
    public static function getTargetAudienceLabel(string $audience): string
    {
        return match ($audience) {
            'professionals' => 'Profesionales',
            'families' => 'Familias',
            'students' => 'Estudiantes',
            'couples' => 'Parejas',
            'singles' => 'Solteros',
            'executives' => 'Ejecutivos',
            'expats' => 'Extranjeros',
            'retirees' => 'Jubilados',
            default => ucfirst($audience),
        };
    }

    /**
     * Get Spanish label for property condition.
     */
    public static function getConditionLabel(?string $condition): string
    {
        if (! $condition) {
            return 'No especificado';
        }

        return match ($condition) {
            'new' => 'Nueva / Estrenar',
            'excellent' => 'Excelente',
            'good' => 'Bueno',
            'fair' => 'Regular',
            'needs_work' => 'Necesita reparaciones',
            'renovated' => 'Remodelado',
            default => ucfirst($condition),
        };
    }

    /**
     * Get Spanish label for building type.
     */
    public static function getBuildingTypeLabel(?string $type): string
    {
        if (! $type) {
            return '';
        }

        return match ($type) {
            'luxury_residential' => 'Residencial de lujo',
            'residential' => 'Residencial',
            'mixed_use' => 'Uso mixto',
            'commercial' => 'Comercial',
            'office' => 'Oficinas',
            'gated_community' => 'Coto privado',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Format target audience array for display.
     *
     * @param  array<string>|string|null  $audience
     */
    public static function formatTargetAudience(array|string|null $audience): string
    {
        if (! $audience) {
            return '';
        }

        if (is_string($audience)) {
            $audience = [$audience];
        }

        return collect($audience)
            ->map(fn ($a) => self::getTargetAudienceLabel($a))
            ->join(', ');
    }

    /**
     * Format occupancy type for display.
     */
    public static function formatOccupancyType(?string $occupancyType): string
    {
        if (! $occupancyType) {
            return '';
        }

        return match ($occupancyType) {
            'single_person_or_couple' => 'Individual/Pareja',
            'single_person' => 'Individual',
            'couple' => 'Pareja',
            'family' => 'Familia',
            'roommates' => 'Roomies',
            'students' => 'Estudiantes',
            default => ucfirst(str_replace('_', ' ', $occupancyType)),
        };
    }

    /**
     * Format property condition for display.
     */
    public static function formatPropertyCondition(?string $condition): string
    {
        return self::getConditionLabel($condition);
    }
}
