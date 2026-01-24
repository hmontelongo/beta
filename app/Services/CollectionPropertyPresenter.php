<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
     * @return Collection<int, array>
     */
    public function prepareProperties(Collection $properties): Collection
    {
        return $properties->map(fn (Property $property, int $index) => $this->prepareProperty($property, $index + 1));
    }

    /**
     * Prepare a single property for rich display.
     *
     * @return array<string, mixed>
     */
    public function prepareProperty(Property $property, int $position): array
    {
        $extractedData = $property->ai_extracted_data ?? [];

        return [
            'position' => $position,
            'id' => $property->id,
            'property' => $property,

            // Images (up to 4) - uses Property model accessor
            'images' => array_slice($property->images, 0, 4),

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
     * Humanize amenity name for display.
     */
    public static function humanizeAmenity(string $amenity): string
    {
        $key = 'amenities.'.strtolower($amenity);
        $translation = __($key);

        return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $amenity));
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
     * Truncate description for display.
     */
    public static function truncateDescription(?string $description, int $limit = 300): ?string
    {
        if (! $description) {
            return null;
        }

        return Str::limit(strip_tags($description), $limit);
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
     * Format money amount.
     */
    public static function formatMoney(float|int $amount, string $currency = 'MXN'): string
    {
        return '$'.number_format($amount).' '.$currency;
    }

    /**
     * Format target audience array for display.
     *
     * @param  array<string>|string  $audience
     */
    public static function formatTargetAudience(array|string $audience): string
    {
        if (is_string($audience)) {
            $audience = [$audience];
        }

        return collect($audience)
            ->map(function ($a) {
                $key = "properties.target_audience.{$a}";
                $translation = __($key);

                return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $a));
            })
            ->join(', ');
    }

    /**
     * Format occupancy type for display.
     */
    public static function formatOccupancyType(string $occupancyType): string
    {
        $key = "properties.occupancy_type.{$occupancyType}";
        $translation = __($key);

        return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $occupancyType));
    }

    /**
     * Format property condition for display.
     */
    public static function formatPropertyCondition(string $condition): string
    {
        $key = "properties.property_condition.{$condition}";
        $translation = __($key);

        return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $condition));
    }
}
