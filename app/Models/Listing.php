<?php

namespace App\Models;

use App\Enums\AiEnrichmentStatus;
use App\Enums\DedupStatus;
use App\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Listing extends Model
{
    /** @use HasFactory<\Database\Factories\ListingFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'ai_status' => AiEnrichmentStatus::class,
            'dedup_status' => DedupStatus::class,
            'operations' => 'array',
            'external_codes' => 'array',
            'raw_data' => 'array',
            'data_quality' => 'array',
            'scraped_at' => 'datetime',
            'ai_processed_at' => 'datetime',
            'dedup_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * @return BelongsTo<DiscoveredListing, $this>
     */
    public function discoveredListing(): BelongsTo
    {
        return $this->belongsTo(DiscoveredListing::class);
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return HasMany<ListingPhone, $this>
     */
    public function phones(): HasMany
    {
        return $this->hasMany(ListingPhone::class);
    }

    /**
     * @return HasMany<ListingImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class);
    }

    /**
     * @return HasOne<AiEnrichment, $this>
     */
    public function aiEnrichment(): HasOne
    {
        return $this->hasOne(AiEnrichment::class);
    }

    /**
     * Get dedup candidates where this listing is listing_a.
     *
     * @return HasMany<DedupCandidate, $this>
     */
    public function dedupCandidatesAsA(): HasMany
    {
        return $this->hasMany(DedupCandidate::class, 'listing_a_id');
    }

    /**
     * Get dedup candidates where this listing is listing_b.
     *
     * @return HasMany<DedupCandidate, $this>
     */
    public function dedupCandidatesAsB(): HasMany
    {
        return $this->hasMany(DedupCandidate::class, 'listing_b_id');
    }

    /**
     * Scope for listings pending AI enrichment.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Listing>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Listing>
     */
    public function scopePendingAiEnrichment($query)
    {
        return $query->where('ai_status', AiEnrichmentStatus::Pending);
    }

    /**
     * Scope for listings pending deduplication.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Listing>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Listing>
     */
    public function scopePendingDedup($query)
    {
        return $query->where('dedup_status', DedupStatus::Pending)
            ->where('ai_status', AiEnrichmentStatus::Completed);
    }
}
