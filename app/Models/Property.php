<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertySubtype;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['agents', 'agencies', 'platforms', 'price_range'];

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
     * Get unique agents from all listings for this property.
     *
     * @return Collection<int, Agent>
     */
    public function getAgentsAttribute(): Collection
    {
        return $this->listings
            ->pluck('agent')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Get unique agencies from all listings for this property.
     *
     * @return Collection<int, Agency>
     */
    public function getAgenciesAttribute(): Collection
    {
        return $this->listings
            ->pluck('agency')
            ->filter()
            ->unique('id')
            ->values();
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
}
