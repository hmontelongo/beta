<?php

namespace App\Models;

use App\Enums\OperationType;
use App\Enums\PropertySourceType;
use App\Enums\PropertyStatus;
use App\Enums\PropertySubtype;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        // Ownership & source
        'user_id',
        'source_type',
        // Operation & pricing (for native properties)
        'operation_type',
        'price',
        'price_currency',
        // Collaboration
        'is_collaborative',
        'commission_split',
        // Location
        'address',
        'interior_number',
        'colonia',
        'city',
        'state',
        'postal_code',
        'latitude',
        'longitude',
        // Property details
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
        'original_description',
        // AI/scraping metadata
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
            // New native upload fields
            'source_type' => PropertySourceType::class,
            'operation_type' => OperationType::class,
            'price' => 'decimal:2',
            'is_collaborative' => 'boolean',
            'commission_split' => 'decimal:2',
            // Existing fields
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

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * The user (agent) who owns this property (for native uploads).
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Images uploaded for this property (native uploads).
     *
     * @return HasMany<PropertyImage, $this>
     */
    public function propertyImages(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('position');
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
     * Collections that include this property.
     *
     * @return BelongsToMany<\App\Models\Collection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Collection::class)
            ->withPivot('position')
            ->withTimestamps();
    }

    // =========================================================================
    // Scopes
    // =========================================================================

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
     * Scope for scraped properties only.
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeScraped(Builder $query): Builder
    {
        return $query->where('source_type', PropertySourceType::Scraped);
    }

    /**
     * Scope for native (agent-uploaded) properties only.
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeNative(Builder $query): Builder
    {
        return $query->where('source_type', PropertySourceType::Native);
    }

    /**
     * Scope for properties owned by a specific user.
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for collaborative properties (visible to all agents).
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeCollaborative(Builder $query): Builder
    {
        return $query->where('is_collaborative', true);
    }

    /**
     * Scope for properties visible to a specific user in search.
     * - All scraped properties (no owner)
     * - Native + collaborative (visible to all)
     * - Native + owned by user (always visible to owner)
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeVisibleTo(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            // All scraped properties
            $q->where('source_type', PropertySourceType::Scraped)
                // OR native + collaborative
                ->orWhere(function (Builder $q) {
                    $q->where('source_type', PropertySourceType::Native)
                        ->where('is_collaborative', true);
                })
                // OR native + owned by current user
                ->orWhere(function (Builder $q) use ($userId) {
                    $q->where('source_type', PropertySourceType::Native)
                        ->where('user_id', $userId);
                });
        });
    }

    /**
     * Scope to filter by operation type (rent/sale).
     * Handles both native and scraped properties.
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeFilterByOperationType(Builder $query, string $operationType): Builder
    {
        return $query->where(function (Builder $q) use ($operationType) {
            // Native properties: filter by direct operation_type field
            $q->where(function (Builder $native) use ($operationType) {
                $native->native()->where('operation_type', $operationType);
            })
            // Scraped properties: filter via listings JSON
                ->orWhere(function (Builder $scraped) use ($operationType) {
                    $scraped->scraped()->whereHas('listings', function (Builder $listing) use ($operationType) {
                        $listing->whereJsonContains('operations', ['type' => $operationType]);
                    });
                });
        });
    }

    /**
     * Scope to filter by price range.
     * Handles both native and scraped properties.
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeFilterByPriceRange(Builder $query, ?int $minPrice = null, ?int $maxPrice = null, ?string $operationType = null): Builder
    {
        return $query->where(function (Builder $q) use ($minPrice, $maxPrice, $operationType) {
            // Native properties: filter by direct price field
            $q->where(function (Builder $native) use ($minPrice, $maxPrice, $operationType) {
                $native->native()
                    ->when($operationType, fn (Builder $q) => $q->where('operation_type', $operationType))
                    ->when($minPrice !== null, fn (Builder $q) => $q->where('price', '>=', $minPrice))
                    ->when($maxPrice !== null, fn (Builder $q) => $q->where('price', '<=', $maxPrice));
            })
            // Scraped properties: filter via listings JSON
                ->orWhere(function (Builder $scraped) use ($minPrice, $maxPrice, $operationType) {
                    $scraped->scraped()->whereHas('listings', function (Builder $listing) use ($minPrice, $maxPrice, $operationType) {
                        $listing->when($operationType, fn (Builder $q) => $q->whereJsonContains('operations', ['type' => $operationType]));
                        $listing->when($minPrice !== null, fn (Builder $q) => $q->whereRaw("JSON_EXTRACT(operations, '$[0].price') >= ?", [$minPrice]));
                        $listing->when($maxPrice !== null, fn (Builder $q) => $q->whereRaw("JSON_EXTRACT(operations, '$[0].price') <= ?", [$maxPrice]));
                    });
                });
        });
    }

    /**
     * Scope to order by price.
     * Handles both native and scraped properties using COALESCE.
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeOrderByPrice(Builder $query, string $direction = 'asc'): Builder
    {
        $aggregate = $direction === 'asc' ? 'MIN' : 'MAX';

        return $query->orderByRaw(
            "COALESCE(properties.price, (SELECT {$aggregate}(JSON_EXTRACT(operations, \"$[0].price\")) FROM listings WHERE listings.property_id = properties.id)) {$direction}"
        );
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Check if this is a native (agent-uploaded) property.
     */
    public function isNative(): bool
    {
        return $this->source_type === PropertySourceType::Native;
    }

    /**
     * Check if this is a scraped property.
     */
    public function isScraped(): bool
    {
        return $this->source_type === PropertySourceType::Scraped;
    }

    /**
     * Check if the given user owns this property.
     */
    public function isOwnedBy(?User $user): bool
    {
        if (! $user || ! $this->user_id) {
            return false;
        }

        return $this->user_id === $user->id;
    }

    /**
     * Mark this property for re-analysis.
     */
    public function markForReanalysis(): void
    {
        $this->update(['needs_reanalysis' => true]);
    }

    // =========================================================================
    // Accessors
    // =========================================================================

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
     * For native properties, uses direct price field.
     * For scraped properties, uses listing operations.
     *
     * @return array{type: string, price: float, currency: string, maintenance_fee: float|null}|null
     */
    public function getPrimaryPriceAttribute(): ?array
    {
        // Native properties: use direct price field
        if ($this->isNative() && $this->price) {
            // Get maintenance fee from ai_extracted_data for native properties
            $maintenanceFee = $this->ai_extracted_data['pricing']['maintenance_fee'] ?? null;

            return [
                'type' => $this->operation_type?->value ?? 'unknown',
                'price' => (float) $this->price,
                'currency' => $this->price_currency ?? 'MXN',
                'maintenance_fee' => $maintenanceFee,
            ];
        }

        // Scraped properties: get from listings
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
     * Get images for display.
     * For native properties, uses PropertyImages.
     * For scraped properties, uses listing raw_data.
     *
     * @return array<string>
     */
    public function getImagesAttribute(): array
    {
        // Native properties: use uploaded images
        if ($this->isNative()) {
            return $this->propertyImages
                ->pluck('url')
                ->toArray();
        }

        // Scraped properties: get from primary listing
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
     * Get the cover image URL.
     */
    public function getCoverImageAttribute(): ?string
    {
        // Native properties: find cover image
        if ($this->isNative()) {
            $cover = $this->propertyImages->firstWhere('is_cover', true)
                ?? $this->propertyImages->first();

            return $cover?->url;
        }

        // Scraped: first image
        return $this->images[0] ?? null;
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

    /**
     * Get the location display string (colonia, city).
     */
    public function getLocationDisplayAttribute(): string
    {
        $parts = array_filter([
            $this->colonia,
            $this->city,
        ]);

        return implode(', ', $parts);
    }
}
