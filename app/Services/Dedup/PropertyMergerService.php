<?php

namespace App\Services\Dedup;

use App\Enums\PropertyStatus;
use App\Models\Listing;
use App\Models\Property;
use App\Models\PropertyConflict;
use Illuminate\Support\Facades\Log;

class PropertyMergerService
{
    /**
     * Fields that can be merged from listing to property.
     *
     * @var array<string>
     */
    protected array $mergeableFields = [
        'address',
        'interior_number',
        'colonia',
        'city',
        'state',
        'postal_code',
        'latitude',
        'longitude',
        'property_type',
        'property_subtype',
        'bedrooms',
        'bathrooms',
        'half_bathrooms',
        'parking_spots',
        'lot_size_m2',
        'built_size_m2',
        'age_years',
    ];

    /**
     * Create a new Property from a Listing.
     */
    public function createPropertyFromListing(Listing $listing): Property
    {
        $rawData = $listing->raw_data ?? [];

        // Build address with fallbacks - use colonia or city if no street address
        $address = $rawData['address'] ?? $rawData['colonia'] ?? $rawData['city'] ?? 'Sin dirección';

        // Required fields need fallback values
        $colonia = $rawData['colonia'] ?? 'Desconocida';
        $city = $rawData['city'] ?? 'Desconocida';
        $state = $rawData['state'] ?? 'Desconocido';
        $propertyType = $rawData['property_type'] ?? 'other';

        $property = Property::create([
            'address' => $address,
            'interior_number' => $rawData['interior_number'] ?? null,
            'colonia' => $colonia,
            'city' => $city,
            'state' => $state,
            'postal_code' => $rawData['postal_code'] ?? null,
            'latitude' => $rawData['latitude'] ?? null,
            'longitude' => $rawData['longitude'] ?? null,
            'property_type' => $propertyType,
            'property_subtype' => $rawData['property_subtype'] ?? null,
            'bedrooms' => $rawData['bedrooms'] ?? null,
            'bathrooms' => $rawData['bathrooms'] ?? null,
            'half_bathrooms' => $rawData['half_bathrooms'] ?? null,
            'parking_spots' => $rawData['parking_spots'] ?? null,
            'lot_size_m2' => $rawData['lot_size_m2'] ?? null,
            'built_size_m2' => $rawData['built_size_m2'] ?? null,
            'age_years' => $rawData['age_years'] ?? null,
            'amenities' => $rawData['amenities'] ?? [],
            'status' => PropertyStatus::Active,
            'confidence_score' => 50, // Initial score, improves with more listings
            'listings_count' => 1,
        ]);

        $listing->update(['property_id' => $property->id]);

        Log::info('Property created from listing', [
            'property_id' => $property->id,
            'listing_id' => $listing->id,
        ]);

        return $property;
    }

    /**
     * Merge a Listing into an existing Property.
     */
    public function mergeListingIntoProperty(Listing $listing, Property $property): Property
    {
        $rawData = $listing->raw_data ?? [];
        $updates = [];
        $conflicts = [];

        foreach ($this->mergeableFields as $field) {
            $newValue = $rawData[$field] ?? null;
            $currentValue = $property->$field;

            if ($newValue === null) {
                continue;
            }

            // If current value is null, use new value
            if ($currentValue === null) {
                $updates[$field] = $newValue;

                continue;
            }

            // Check for conflict
            if (! $this->valuesMatch($field, $currentValue, $newValue)) {
                // Decide which value to use
                $resolvedValue = $this->resolveFieldValue($field, $currentValue, $newValue);

                if ($resolvedValue !== $currentValue) {
                    $updates[$field] = $resolvedValue;
                }

                // Record the conflict
                $conflicts[] = [
                    'field' => $field,
                    'canonical_value' => $this->stringifyValue($currentValue),
                    'source_value' => $this->stringifyValue($newValue),
                ];
            }
        }

        // Merge amenities
        $newAmenities = $rawData['amenities'] ?? [];
        if (! empty($newAmenities)) {
            $currentAmenities = $property->amenities ?? [];
            $mergedAmenities = array_values(array_unique(array_merge($currentAmenities, $newAmenities)));
            $updates['amenities'] = $mergedAmenities;
        }

        // Update listings count
        $updates['listings_count'] = $property->listings()->count() + 1;

        // Increase confidence score (more listings = more confidence)
        $updates['confidence_score'] = min(100, $property->confidence_score + 10);

        // Apply updates
        if (! empty($updates)) {
            $property->update($updates);
        }

        // Link listing to property
        $listing->update(['property_id' => $property->id]);

        // Record conflicts
        foreach ($conflicts as $conflict) {
            $this->recordConflict($property, $listing, $conflict);
        }

        Log::info('Listing merged into property', [
            'property_id' => $property->id,
            'listing_id' => $listing->id,
            'fields_updated' => array_keys($updates),
            'conflicts_count' => count($conflicts),
        ]);

        return $property->fresh();
    }

    /**
     * Check if two values are considered a match.
     */
    protected function valuesMatch(string $field, mixed $current, mixed $new): bool
    {
        // Numeric fields - allow small tolerance
        if (in_array($field, ['lot_size_m2', 'built_size_m2'])) {
            $current = (float) $current;
            $new = (float) $new;

            if ($current == 0 || $new == 0) {
                return $current == $new;
            }

            return abs($current - $new) / max($current, $new) <= 0.05; // 5% tolerance
        }

        // Coordinates - allow small tolerance
        if (in_array($field, ['latitude', 'longitude'])) {
            return abs((float) $current - (float) $new) <= 0.0001;
        }

        // String fields - case-insensitive
        if (is_string($current) && is_string($new)) {
            return mb_strtolower(trim($current)) === mb_strtolower(trim($new));
        }

        return $current == $new;
    }

    /**
     * Resolve which value to use when there's a conflict.
     * Strategy: prefer more specific/complete data.
     */
    protected function resolveFieldValue(string $field, mixed $current, mixed $new): mixed
    {
        // Numeric fields - prefer non-zero, larger values
        if (in_array($field, ['bedrooms', 'bathrooms', 'half_bathrooms', 'parking_spots', 'age_years'])) {
            $currentVal = (int) $current;
            $newVal = (int) $new;

            // Prefer non-zero value
            if ($currentVal == 0 && $newVal > 0) {
                return $newVal;
            }
            if ($newVal == 0 && $currentVal > 0) {
                return $currentVal;
            }

            // Keep current (established data takes precedence)
            return $currentVal;
        }

        // Size fields - prefer larger (usually more accurate)
        if (in_array($field, ['lot_size_m2', 'built_size_m2'])) {
            return max((float) $current, (float) $new);
        }

        // Address fields - prefer longer (more complete)
        if (in_array($field, ['address', 'colonia', 'city', 'state'])) {
            $currentLen = mb_strlen((string) $current);
            $newLen = mb_strlen((string) $new);

            return $newLen > $currentLen ? $new : $current;
        }

        // Coordinates - prefer the one with more precision
        if (in_array($field, ['latitude', 'longitude'])) {
            $currentPrecision = strlen(substr(strrchr((string) $current, '.'), 1));
            $newPrecision = strlen(substr(strrchr((string) $new, '.'), 1));

            return $newPrecision > $currentPrecision ? $new : $current;
        }

        // Default: keep current value (established data takes precedence)
        return $current;
    }

    /**
     * Record a field conflict for manual review.
     *
     * @param  array{field: string, canonical_value: string, source_value: string}  $conflict
     */
    protected function recordConflict(Property $property, Listing $listing, array $conflict): PropertyConflict
    {
        return PropertyConflict::create([
            'property_id' => $property->id,
            'listing_id' => $listing->id,
            'field' => $conflict['field'],
            'canonical_value' => $conflict['canonical_value'],
            'source_value' => $conflict['source_value'],
            'resolved' => false,
        ]);
    }

    /**
     * Convert value to string for storage.
     */
    protected function stringifyValue(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return (string) $value;
    }
}
