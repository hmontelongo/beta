<?php

namespace App\Livewire\Agents\Properties;

use App\Models\Listing;
use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.agent')]
class Show extends Component
{
    public Property $property;

    /** @var array<int> Collection of property IDs (UI mockup state) */
    public array $collection = [];

    public function mount(Property $property): void
    {
        $this->property = $property->load([
            'listings.platform',
            'listings.publisher',
            'publishers',
        ]);
    }

    /**
     * Get the primary listing (most recently scraped).
     */
    #[Computed]
    public function primaryListing(): ?Listing
    {
        return $this->property->listings
            ->sortByDesc('scraped_at')
            ->first();
    }

    /**
     * Get images from the primary listing only (avoids duplicates from multiple sources).
     *
     * @return array<string>
     */
    #[Computed]
    public function images(): array
    {
        $listing = $this->primaryListing;

        if (! $listing) {
            return [];
        }

        return collect($listing->raw_data['images'] ?? [])
            ->map(fn (array|string $img): string => is_array($img) ? $img['url'] : $img)
            ->filter(fn (string $url): bool => ! str_contains($url, '.svg')
                && ! str_contains($url, 'placeholder')
                && ! str_contains($url, 'icon')
                && preg_match('/\.(jpg|jpeg|png|webp)/i', $url)
            )
            ->take(30)
            ->values()
            ->toArray();
    }

    /**
     * Get the primary price for display.
     *
     * @return array{type: string, price: float, currency: string, maintenance_fee: float|null}|null
     */
    #[Computed]
    public function primaryPrice(): ?array
    {
        foreach ($this->property->listings as $listing) {
            $operations = $listing->raw_data['operations'] ?? [];
            foreach ($operations as $op) {
                if (($op['price'] ?? 0) > 0) {
                    return [
                        'type' => $op['type'] ?? 'unknown',
                        'price' => (float) $op['price'],
                        'currency' => $op['currency'] ?? 'MXN',
                        'maintenance_fee' => $op['maintenance_fee'] ?? null,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get description - prefer AI property description, then raw.
     */
    #[Computed]
    public function description(): ?string
    {
        if ($this->property->description) {
            return $this->property->description;
        }

        return $this->primaryListing?->raw_data['description'] ?? null;
    }

    /**
     * Get amenities formatted for display (flat list fallback).
     *
     * @return array<string>
     */
    #[Computed]
    public function amenities(): array
    {
        return $this->property->amenities ?? [];
    }

    /**
     * Get unique publishers for this property.
     *
     * @return Collection<int, \App\Models\Publisher>
     */
    #[Computed]
    public function publishers(): Collection
    {
        return $this->property->publishers;
    }

    /**
     * Get AI-extracted data if available.
     *
     * @return array{pricing?: array, terms?: array, amenities_categorized?: array, location?: array, inferred?: array}|null
     */
    #[Computed]
    public function extractedData(): ?array
    {
        return $this->property->ai_extracted_data;
    }

    /**
     * Check if AI-extracted data is available.
     */
    #[Computed]
    public function hasExtractedData(): bool
    {
        return ! empty($this->property->ai_extracted_data);
    }

    /**
     * Get categorized amenities from AI extraction.
     *
     * @return array{in_unit?: array, building?: array, services?: array, available_extra?: array}|null
     */
    #[Computed]
    public function categorizedAmenities(): ?array
    {
        return $this->extractedData['amenities_categorized'] ?? null;
    }

    /**
     * Get top amenities for quick display (3-4 most important).
     *
     * @return array<string>
     */
    #[Computed]
    public function topAmenities(): array
    {
        // Priority amenities that agents care about most
        $priorityAmenities = [
            'swimming_pool', 'pool', 'alberca',
            '24_hour_security', 'security', 'seguridad',
            'gated_community', 'coto_cerrado',
            'covered_parking', 'parking', 'estacionamiento',
            'gym', 'gimnasio',
            'furnished', 'amueblado',
            'pet_friendly', 'mascotas',
            'elevator', 'elevador',
            'roof_garden', 'terrace', 'terraza',
        ];

        $allAmenities = $this->amenities;
        $top = [];

        // First, try to get priority amenities
        foreach ($allAmenities as $amenity) {
            $normalized = strtolower(str_replace([' ', '-'], '_', $amenity));
            foreach ($priorityAmenities as $priority) {
                if (str_contains($normalized, $priority)) {
                    $top[] = $amenity;
                    break;
                }
            }
            if (count($top) >= 4) {
                break;
            }
        }

        // Fill remaining slots if needed
        if (count($top) < 4) {
            foreach ($allAmenities as $amenity) {
                if (! in_array($amenity, $top)) {
                    $top[] = $amenity;
                    if (count($top) >= 4) {
                        break;
                    }
                }
            }
        }

        return $top;
    }

    /**
     * Get rental terms from AI extraction.
     *
     * @return array{deposit_months?: int, advance_months?: int, income_proof_months?: int, guarantor_required?: bool, pets_allowed?: bool, max_occupants?: int}|null
     */
    #[Computed]
    public function rentalTerms(): ?array
    {
        return $this->extractedData['terms'] ?? null;
    }

    /**
     * Get building/location info from AI extraction.
     *
     * @return array{building_name?: string, building_type?: string, nearby_landmarks?: array}|null
     */
    #[Computed]
    public function buildingInfo(): ?array
    {
        return $this->extractedData['location'] ?? null;
    }

    /**
     * Get AI-inferred property insights.
     *
     * @return array{ideal_for?: string, best_for?: string, condition?: string}|null
     */
    #[Computed]
    public function propertyInsights(): ?array
    {
        return $this->extractedData['inferred'] ?? null;
    }

    /**
     * Get enhanced pricing details from AI extraction.
     *
     * @return array{whats_included?: array, additional_costs?: array}|null
     */
    #[Computed]
    public function pricingDetails(): ?array
    {
        return $this->extractedData['pricing'] ?? null;
    }

    /**
     * Calculate price per square meter.
     */
    #[Computed]
    public function pricePerM2(): ?float
    {
        $price = $this->primaryPrice;
        $size = $this->property->built_size_m2;

        if (! $price || ! $size || $size <= 0) {
            return null;
        }

        return round($price['price'] / $size, 0);
    }

    /**
     * Get description with source indicator.
     *
     * @return array{text: string|null, source: string}
     */
    #[Computed]
    public function descriptionWithSource(): array
    {
        if ($this->property->description) {
            return [
                'text' => $this->property->description,
                'source' => 'ai',
            ];
        }

        return [
            'text' => $this->primaryListing?->raw_data['description'] ?? null,
            'source' => 'raw',
        ];
    }

    /**
     * Humanize amenity name for display.
     */
    public function humanizeAmenity(string $amenity): string
    {
        $translations = [
            // Unit amenities
            'integrated_kitchen' => 'Cocina integral',
            'terrace' => 'Terraza',
            'laundry_room' => 'Cuarto de lavado',
            'balcony' => 'Balcón',
            'closet' => 'Clóset',
            'walk_in_closet' => 'Vestidor',
            'air_conditioning' => 'Aire acondicionado',
            'ac' => 'Aire acondicionado',
            'heating' => 'Calefacción',
            'washer' => 'Lavadora',
            'dryer' => 'Secadora',
            'dishwasher' => 'Lavavajillas',
            'furnished' => 'Amueblado',
            'semi_furnished' => 'Semi-amueblado',
            'unfurnished' => 'Sin amueblar',
            'granite_countertops' => 'Cubiertas de granito',
            'natural_gas' => 'Gas natural',

            // Building amenities
            'rooftop' => 'Roof top',
            'jacuzzi' => 'Jacuzzi',
            'elevator' => 'Elevador',
            'security_booth' => 'Caseta de vigilancia',
            'roof_garden' => 'Roof garden',
            'swimming_pool' => 'Alberca',
            'pool' => 'Alberca',
            'gym' => 'Gimnasio',
            'playground' => 'Área de juegos',
            'party_room' => 'Salón de fiestas',
            'business_center' => 'Business center',
            'coworking' => 'Coworking',
            'pet_area' => 'Área de mascotas',
            'garden' => 'Jardín',
            'common_area' => 'Área común',
            'bbq_area' => 'Área de asador',
            'grill' => 'Asador',
            'multipurpose_room' => 'Salón de usos múltiples',
            'meeting_room' => 'Sala de juntas',
            'fountain' => 'Fuente',
            'bike_parking' => 'Biciestacionamiento',
            'accessibility_features' => 'Accesibilidad',
            'convenience_store' => 'Tienda de conveniencia',
            'restaurant' => 'Restaurante',

            // Services
            'security' => 'Seguridad 24h',
            'security_24h' => 'Seguridad 24h',
            '24_hour_security' => 'Seguridad 24h',
            'guard_house' => 'Caseta de guardia',
            'concierge' => 'Concierge',
            'maintenance' => 'Mantenimiento',
            'cleaning' => 'Limpieza',
            'valet_parking' => 'Valet parking',
            'covered_parking' => 'Estacionamiento techado',
            'visitor_parking' => 'Estacionamiento visitantes',
            'storage' => 'Bodega',
            'gated_community' => 'Coto cerrado',
            'disabled_access' => 'Acceso para discapacitados',
            'wheelchair_access' => 'Acceso para silla de ruedas',
            'package_reception' => 'Recepción de paquetes',
            'security_cameras' => 'Cámaras de seguridad',

            // Extras
            'pet_friendly' => 'Mascotas permitidas',
            'solar_panels' => 'Paneles solares',
            'water_tank' => 'Cisterna',
            'generator' => 'Planta de luz',
        ];

        return $translations[strtolower($amenity)] ?? ucfirst(str_replace('_', ' ', $amenity));
    }

    /**
     * Get emoji icon for a landmark type.
     */
    public function getLandmarkIcon(string $type): string
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
     * Format target audience array for display.
     *
     * @param  array<string>|string  $audience
     */
    public function formatTargetAudience(array|string $audience): string
    {
        if (is_string($audience)) {
            $audience = [$audience];
        }

        $labels = [
            'young_professionals' => 'Profesionales',
            'couples' => 'Parejas',
            'families' => 'Familias',
            'students' => 'Estudiantes',
            'singles' => 'Solteros',
            'retirees' => 'Jubilados',
            'executives' => 'Ejecutivos',
        ];

        return collect($audience)
            ->map(fn ($a) => $labels[$a] ?? ucfirst(str_replace('_', ' ', $a)))
            ->join(', ');
    }

    /**
     * Format occupancy type for display.
     */
    public function formatOccupancyType(string $occupancyType): string
    {
        $labels = [
            'single_person_or_couple' => 'Individual/Pareja',
            'single_person' => 'Individual',
            'couple' => 'Pareja',
            'family' => 'Familia',
            'roommates' => 'Roomies',
            'students' => 'Estudiantes',
        ];

        return $labels[$occupancyType] ?? ucfirst(str_replace('_', ' ', $occupancyType));
    }

    public function toggleCollection(): void
    {
        if (in_array($this->property->id, $this->collection)) {
            $this->collection = array_values(array_filter(
                $this->collection,
                fn ($id) => $id !== $this->property->id
            ));
        } else {
            $this->collection[] = $this->property->id;
        }
    }

    public function isInCollection(): bool
    {
        return in_array($this->property->id, $this->collection);
    }

    public function render(): View
    {
        return view('livewire.agents.properties.show')
            ->title($this->property->address ?? 'Propiedad');
    }
}
