<?php

namespace App\Livewire\Agents\Properties;

use App\Livewire\Concerns\HasActiveCollection;
use App\Models\Collection as CollectionModel;
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
    use HasActiveCollection;

    public Property $property;

    public function mount(Property $property): void
    {
        $this->property = $property->load([
            'listings.platform',
            'listings.publisher',
            'publishers',
        ]);

        // Restore active collection from session (shared with search page)
        $this->initializeActiveCollection();
    }

    /**
     * Get the active collection model.
     */
    #[Computed]
    public function activeCollection(): ?CollectionModel
    {
        return $this->getActiveCollectionModel();
    }

    /**
     * Clear collection-related computed property caches.
     */
    protected function clearCollectionCaches(): void
    {
        unset($this->activeCollection);
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
     * Check if rental terms have meaningful data to display.
     */
    #[Computed]
    public function hasRentalTermsData(): bool
    {
        $terms = $this->rentalTerms;

        if (! $terms) {
            return false;
        }

        return ! empty($terms['deposit_months'])
            || ! empty($terms['advance_months'])
            || ! empty($terms['income_proof_months'])
            || isset($terms['guarantor_required'])
            || isset($terms['pets_allowed'])
            || ! empty($terms['max_occupants']);
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
        $key = 'amenities.'.strtolower($amenity);
        $translation = __($key);

        return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $amenity));
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
    public function formatOccupancyType(string $occupancyType): string
    {
        $key = "properties.occupancy_type.{$occupancyType}";
        $translation = __($key);

        return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $occupancyType));
    }

    /**
     * Format property condition for display.
     */
    public function formatPropertyCondition(string $condition): string
    {
        $key = "properties.property_condition.{$condition}";
        $translation = __($key);

        return $translation !== $key ? $translation : ucfirst(str_replace('_', ' ', $condition));
    }

    public function toggleCollection(): void
    {
        $collection = $this->ensureActiveCollection();

        if ($collection->properties()->where('property_id', $this->property->id)->exists()) {
            $collection->properties()->detach($this->property->id);
        } else {
            $maxPosition = $collection->properties()->max('position') ?? 0;
            $collection->properties()->attach($this->property->id, ['position' => $maxPosition + 1]);
        }

        // Clear computed property cache
        unset($this->activeCollection);
    }

    public function isInCollection(): bool
    {
        $collection = $this->activeCollection;

        if (! $collection) {
            return false;
        }

        return $collection->properties()->where('property_id', $this->property->id)->exists();
    }

    public function render(): View
    {
        return view('livewire.agents.properties.show')
            ->title($this->property->address ?? 'Propiedad');
    }
}
