<?php

namespace App\Services;

use App\Enums\PropertySubtype;
use App\Enums\PropertyType;
use App\Models\Property;

/**
 * Provides consistent formatting and translation for property display across all views.
 * Uses static methods for formatted display strings that need context-awareness.
 *
 * Model accessors handle computed DATA (primary_price, images, top_amenities).
 * This presenter handles FORMATTED STRINGS with proper Spanish translations.
 */
class PropertyPresenter
{
    private const LOCALE = 'es';

    // ═══════════════════════════════════════════════════════════════════════════
    // PRICE FORMATTING
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Format a price array for display.
     *
     * @param  array{type?: string, price?: float|int, currency?: string}|null  $price
     */
    public static function formatPrice(?array $price, bool $withPeriod = true): string
    {
        if (! $price || ! isset($price['price']) || $price['price'] <= 0) {
            return 'Consultar';
        }

        $formatted = '$'.number_format($price['price']);

        if (isset($price['currency']) && $price['currency'] !== 'MXN') {
            $formatted .= ' '.$price['currency'];
        }

        if ($withPeriod && isset($price['type']) && $price['type'] === 'rent') {
            $formatted .= '/mes';
        }

        return $formatted;
    }

    /**
     * Format a price in compact form for cards.
     *
     * @param  array{price?: float|int, currency?: string}|null  $price
     */
    public static function formatPriceCompact(?array $price): string
    {
        if (! $price || ! isset($price['price']) || $price['price'] <= 0) {
            return 'Consultar';
        }

        $value = $price['price'];

        if ($value >= 1_000_000) {
            return '$'.number_format($value / 1_000_000, 1).'M';
        }

        if ($value >= 1_000) {
            return '$'.number_format($value / 1_000, 0).'K';
        }

        return '$'.number_format($value);
    }

    /**
     * Format price per square meter.
     */
    public static function formatPricePerM2(?float $pricePerM2): string
    {
        if (! $pricePerM2 || $pricePerM2 <= 0) {
            return '';
        }

        return '$'.number_format($pricePerM2, 0).'/m²';
    }

    /**
     * Format maintenance fee for display.
     *
     * @param  array{amount?: float|int, period?: string, note?: string}|float|int|null  $fee
     */
    public static function formatMaintenanceFee(array|float|int|null $fee): ?string
    {
        if (! $fee) {
            return null;
        }

        if (is_numeric($fee)) {
            return '+ $'.number_format($fee).'/mes';
        }

        if (! isset($fee['amount']) || $fee['amount'] <= 0) {
            return null;
        }

        $period = match ($fee['period'] ?? 'monthly') {
            'monthly' => '/mes',
            'yearly' => '/año',
            'weekly' => '/semana',
            default => '',
        };

        return '+ $'.number_format($fee['amount']).$period;
    }

    /**
     * Get Spanish label for operation type (rent/sale).
     */
    public static function operationTypeLabel(?string $type): string
    {
        return match ($type) {
            'rent' => __('property.operation_type.rent', [], self::LOCALE),
            'sale' => __('property.operation_type.sale', [], self::LOCALE),
            default => 'Consultar',
        };
    }

    /**
     * Get badge color for operation type.
     */
    public static function operationTypeBadgeColor(?string $type): string
    {
        return match ($type) {
            'rent' => 'blue',
            'sale' => 'green',
            default => 'zinc',
        };
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PROPERTY SPECS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Format bedroom count with proper Spanish.
     */
    public static function formatBedrooms(?int $count, bool $abbrev = false): string
    {
        if ($count === null || $count < 0) {
            return '';
        }

        if ($abbrev) {
            return $count.' '.__('property.specs.bedroom_abbrev', [], self::LOCALE);
        }

        $label = $count === 1
            ? __('property.specs.bedroom', [], self::LOCALE)
            : __('property.specs.bedrooms', [], self::LOCALE);

        return $count.' '.$label;
    }

    /**
     * Format bathroom count with proper Spanish.
     */
    public static function formatBathrooms(?int $count, bool $abbrev = false): string
    {
        if ($count === null || $count < 0) {
            return '';
        }

        if ($abbrev) {
            $label = $count === 1
                ? __('property.specs.bathroom_abbrev', [], self::LOCALE)
                : __('property.specs.bathrooms_abbrev', [], self::LOCALE);

            return $count.' '.$label;
        }

        $label = $count === 1
            ? __('property.specs.bathroom', [], self::LOCALE)
            : __('property.specs.bathrooms', [], self::LOCALE);

        return $count.' '.$label;
    }

    /**
     * Format half bathroom count with proper Spanish.
     */
    public static function formatHalfBathrooms(?int $count): string
    {
        if ($count === null || $count <= 0) {
            return '';
        }

        $label = $count === 1
            ? __('property.specs.half_bathroom', [], self::LOCALE)
            : __('property.specs.half_bathrooms', [], self::LOCALE);

        return $count.' '.$label;
    }

    /**
     * Format parking spots count with proper Spanish.
     */
    public static function formatParking(?int $count, bool $abbrev = false): string
    {
        if ($count === null || $count < 0) {
            return '';
        }

        if ($abbrev) {
            return $count.' '.__('property.specs.parking_abbrev', [], self::LOCALE);
        }

        $label = $count === 1
            ? __('property.specs.parking', [], self::LOCALE)
            : __('property.specs.parkings', [], self::LOCALE);

        return $count.' '.$label;
    }

    /**
     * Format built size in m².
     */
    public static function formatBuiltSize(?float $m2): string
    {
        if (! $m2 || $m2 <= 0) {
            return '';
        }

        return number_format($m2, 0).' m²';
    }

    /**
     * Format lot size in m².
     */
    public static function formatLotSize(?float $m2): string
    {
        if (! $m2 || $m2 <= 0) {
            return '';
        }

        return number_format($m2, 0).' m² '.__('property.specs.lot', [], self::LOCALE);
    }

    /**
     * Format property age with proper Spanish (años with ñ).
     */
    public static function formatAge(?int $years): string
    {
        if ($years === null || $years < 0) {
            return '';
        }

        if ($years === 0) {
            return __('property.specs.new_construction', [], self::LOCALE);
        }

        $label = $years === 1
            ? __('property.specs.year', [], self::LOCALE)
            : __('property.specs.years', [], self::LOCALE);

        return $years.' '.$label;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // LABELS (Spanish translations)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Get Spanish label for property type.
     */
    public static function propertyTypeLabel(?PropertyType $type): string
    {
        if (! $type) {
            return 'Propiedad';
        }

        return $type->labelEs();
    }

    /**
     * Get Spanish label for property subtype.
     */
    public static function propertySubtypeLabel(?PropertySubtype $type): string
    {
        if (! $type) {
            return '';
        }

        return $type->labelEs();
    }

    /**
     * Get Spanish label for property condition.
     */
    public static function conditionLabel(?string $condition): string
    {
        if (! $condition) {
            return '';
        }

        $key = "property.property_condition.{$condition}";
        $translation = __($key, [], self::LOCALE);

        return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $condition));
    }

    /**
     * Get Spanish label for freshness status.
     */
    public static function freshnessLabel(string $status): string
    {
        return match ($status) {
            'fresh' => __('property.freshness.fresh', [], self::LOCALE),
            'recent' => __('property.freshness.recent', [], self::LOCALE),
            'stale' => __('property.freshness.stale', [], self::LOCALE),
            default => $status,
        };
    }

    /**
     * Get Spanish label for building type.
     */
    public static function buildingTypeLabel(?string $type): string
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
     * Get Spanish label for target audience.
     */
    public static function targetAudienceLabel(string $audience): string
    {
        $key = "property.target_audience.{$audience}";
        $translation = __($key, [], self::LOCALE);

        return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $audience));
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
            ->map(fn ($a) => self::targetAudienceLabel($a))
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

        $key = "property.occupancy_type.{$occupancyType}";
        $translation = __($key, [], self::LOCALE);

        return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $occupancyType));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // AMENITIES
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Humanize amenity name for display using translation files.
     */
    public static function humanizeAmenity(string $amenity): string
    {
        $key = 'amenities.'.strtolower($amenity);
        $translation = __($key, [], self::LOCALE);

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

    // ═══════════════════════════════════════════════════════════════════════════
    // LOCATION
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Format location for display.
     */
    public static function formatLocation(?string $colonia, ?string $city): string
    {
        $parts = array_filter([$colonia, $city]);

        return implode(', ', $parts);
    }

    /**
     * Format full address from a property.
     */
    public static function formatFullAddress(Property $property): string
    {
        $parts = array_filter([
            $property->address,
            $property->interior_number ? 'Int. '.$property->interior_number : null,
            $property->colonia,
            $property->city,
            $property->state,
            $property->postal_code ? 'C.P. '.$property->postal_code : null,
        ]);

        return implode(', ', $parts);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ICONS (SVG strings for consistent display)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * SVG icon for bedrooms.
     */
    public static function bedroomIcon(string $class = 'size-5'): string
    {
        return '<svg class="'.$class.'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
        </svg>';
    }

    /**
     * SVG icon for bathrooms (shower/water droplet).
     */
    public static function bathroomIcon(string $class = 'size-5'): string
    {
        // Bathtub icon
        return '<svg class="'.$class.'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h18v2a4 4 0 01-4 4H7a4 4 0 01-4-4v-2zM5 13V6a2 2 0 012-2h1a2 2 0 012 2v1m2-3v2m0 0a1 1 0 100 2 1 1 0 000-2z" />
        </svg>';
    }

    /**
     * SVG icon for parking.
     */
    public static function parkingIcon(string $class = 'size-5'): string
    {
        return '<svg class="'.$class.'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
        </svg>';
    }

    /**
     * SVG icon for size/area.
     */
    public static function sizeIcon(string $class = 'size-5'): string
    {
        return '<svg class="'.$class.'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
        </svg>';
    }

    /**
     * SVG icon for location/map pin.
     */
    public static function locationIcon(string $class = 'size-5'): string
    {
        return '<svg class="'.$class.'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
        </svg>';
    }
}
