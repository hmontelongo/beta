<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertySubtype;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory;

    protected $fillable = [
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
        'amenities',
        'description',
        'ai_unification',
        'ai_extracted_data',
        'ai_unified_at',
        'needs_reanalysis',
        'discrepancies',
        'status',
        'confidence_score',
        'listings_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'property_type' => PropertyType::class,
            'property_subtype' => PropertySubtype::class,
            'lot_size_m2' => 'decimal:2',
            'built_size_m2' => 'decimal:2',
            'amenities' => 'array',
            'status' => PropertyStatus::class,
            'ai_unification' => 'array',
            'ai_extracted_data' => 'array',
            'ai_unified_at' => 'datetime',
            'needs_reanalysis' => 'boolean',
            'discrepancies' => 'array',
        ];
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * @return HasMany<ListingGroup, $this>
     */
    public function listingGroups(): HasMany
    {
        return $this->hasMany(ListingGroup::class);
    }

    /**
     * @return HasMany<PropertyConflict, $this>
     */
    public function conflicts(): HasMany
    {
        return $this->hasMany(PropertyConflict::class);
    }

    /**
     * Scope for properties that need AI re-analysis.
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeNeedsReanalysis(Builder $query): Builder
    {
        return $query->where('needs_reanalysis', true);
    }

    /**
     * Mark this property for re-analysis.
     */
    public function markForReanalysis(): void
    {
        $this->update(['needs_reanalysis' => true]);
    }

    /**
     * @return HasMany<PropertyVerification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(PropertyVerification::class);
    }

    /**
     * @return BelongsToMany<Publisher, $this>
     */
    public function publishers(): BelongsToMany
    {
        return $this->belongsToMany(Publisher::class)->withTimestamps();
    }

    /**
     * Get unique platforms from all listings for this property.
     *
     * @return Collection<int, Platform>
     */
    public function getPlatformsAttribute(): Collection
    {
        return $this->listings
            ->pluck('platform')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Get the price range from all listings' operations.
     *
     * @return array{min: float|null, max: float|null, currency: string}
     */
    public function getPriceRangeAttribute(): array
    {
        $prices = $this->listings
            ->flatMap(fn ($listing) => $listing->raw_data['operations'] ?? [])
            ->filter(fn ($op) => isset($op['price']) && $op['price'] > 0)
            ->pluck('price')
            ->map(fn ($price) => (float) $price);

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
            'currency' => 'MXN',
        ];
    }

    /**
     * Get the most recent scraped_at timestamp from all listings.
     */
    public function getLastScrapedAtAttribute(): ?\Carbon\Carbon
    {
        return $this->listings->max('scraped_at');
    }

    /**
     * Get the primary listing (most recently scraped).
     */
    public function getPrimaryListingAttribute(): ?Listing
    {
        return $this->listings->sortByDesc('scraped_at')->first();
    }

    /**
     * Get the primary price for display.
     *
     * @return array{type: string, price: float, currency: string, maintenance_fee: float|null}|null
     */
    public function getPrimaryPriceAttribute(): ?array
    {
        foreach ($this->listings as $listing) {
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
     * Calculate price per square meter.
     */
    public function getPricePerM2Attribute(): ?float
    {
        $price = $this->primary_price;
        $size = $this->built_size_m2;

        if (! $price || ! $size || $size <= 0) {
            return null;
        }

        return round($price['price'] / $size, 0);
    }

    /**
     * Get description - prefer AI property description, then raw from listing.
     */
    public function getDescriptionTextAttribute(): ?string
    {
        if ($this->description) {
            return $this->description;
        }

        return $this->primary_listing?->raw_data['description'] ?? null;
    }

    /**
     * Get images from the primary listing.
     *
     * @return array<string>
     */
    public function getImagesAttribute(): array
    {
        $listing = $this->primary_listing;

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
            ->values()
            ->toArray();
    }

    /**
     * Get top amenities for quick display (priority amenities first).
     *
     * @return array<string>
     */
    public function getTopAmenitiesAttribute(): array
    {
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

        $allAmenities = $this->amenities ?? [];
        $top = [];

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
     * Get maintenance fee from listing operations or AI-extracted data.
     *
     * @return array{amount: float, period: string, note: string|null}|null
     */
    public function getMaintenanceFeeAttribute(): ?array
    {
        // First try from AI-extracted pricing extra_costs
        $extraCosts = $this->ai_extracted_data['pricing']['extra_costs'] ?? [];
        foreach ($extraCosts as $cost) {
            if (($cost['item'] ?? '') === 'maintenance' && ! empty($cost['price'])) {
                return [
                    'amount' => $cost['price'],
                    'period' => $cost['period'] ?? 'monthly',
                    'note' => $cost['note'] ?? null,
                ];
            }
        }

        // Then try from listing operations
        foreach ($this->listings as $listing) {
            $operations = $listing->raw_data['operations'] ?? [];
            foreach ($operations as $op) {
                if (! empty($op['maintenance_fee'])) {
                    return [
                        'amount' => $op['maintenance_fee'],
                        'period' => 'monthly',
                        'note' => null,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get full address string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street_address,
            $this->colonia,
            $this->city,
            $this->state,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }
}
