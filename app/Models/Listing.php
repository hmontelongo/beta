<?php

namespace App\Models;

use App\Enums\DedupStatus;
use App\Enums\ListingGroupStatus;
use App\Enums\ListingPipelineStatus;
use App\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'dedup_status' => DedupStatus::class,
            'operations' => 'array',
            'external_codes' => 'array',
            'raw_data' => 'array',
            'data_quality' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'geocoded_at' => 'datetime',
            'scraped_at' => 'datetime',
            'dedup_checked_at' => 'datetime',
            'is_primary_in_group' => 'boolean',
        ];
    }

    /**
     * Get the computed pipeline status based on geocode_status, dedup_status, and group status.
     */
    public function getPipelineStatusAttribute(): ListingPipelineStatus
    {
        // Terminal states take precedence
        if ($this->dedup_status === DedupStatus::Failed) {
            return ListingPipelineStatus::Failed;
        }

        if ($this->dedup_status === DedupStatus::Cancelled) {
            return ListingPipelineStatus::Cancelled;
        }

        // Completed - has property
        if ($this->dedup_status === DedupStatus::Completed) {
            return ListingPipelineStatus::Completed;
        }

        // Awaiting geocoding
        if ($this->geocode_status !== 'success') {
            return ListingPipelineStatus::AwaitingGeocoding;
        }

        // Pending dedup (geocoded but not yet processed)
        if ($this->dedup_status === DedupStatus::Pending) {
            return ListingPipelineStatus::AwaitingDedup;
        }

        // Processing dedup
        if ($this->dedup_status === DedupStatus::Processing) {
            return ListingPipelineStatus::ProcessingDedup;
        }

        // Waiting for a group to resolve before re-processing
        if ($this->dedup_status === DedupStatus::Waiting) {
            return ListingPipelineStatus::WaitingForGroup;
        }

        // Unique - queued for direct AI property creation (no group needed)
        if ($this->dedup_status === DedupStatus::Unique) {
            return ListingPipelineStatus::QueuedForAi;
        }

        // Grouped - check group status for more detail
        if ($this->dedup_status === DedupStatus::Grouped && $this->listingGroup) {
            return match ($this->listingGroup->status) {
                ListingGroupStatus::PendingReview => ListingPipelineStatus::NeedsReview,
                ListingGroupStatus::PendingAi => ListingPipelineStatus::QueuedForAi,
                ListingGroupStatus::ProcessingAi => ListingPipelineStatus::ProcessingAi,
                default => ListingPipelineStatus::Completed,
            };
        }

        return ListingPipelineStatus::AwaitingDedup;
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
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
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
     * @return BelongsTo<ListingGroup, $this>
     */
    public function listingGroup(): BelongsTo
    {
        return $this->belongsTo(ListingGroup::class);
    }

    /**
     * The group this listing is waiting for to resolve before re-processing.
     *
     * @return BelongsTo<ListingGroup, $this>
     */
    public function waitingForGroup(): BelongsTo
    {
        return $this->belongsTo(ListingGroup::class, 'waiting_for_group_id');
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
     * Scope for listings pending deduplication.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopePendingDedup(Builder $query): Builder
    {
        return $query->where('dedup_status', DedupStatus::Pending);
    }

    /**
     * Scope for listings that have been grouped and are awaiting property creation.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeGrouped(Builder $query): Builder
    {
        return $query->where('dedup_status', DedupStatus::Grouped);
    }

    /**
     * Scope for unique listings awaiting direct property creation.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeUnique(Builder $query): Builder
    {
        return $query->where('dedup_status', DedupStatus::Unique);
    }

    /**
     * Scope for listings with completed property assignment.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('dedup_status', DedupStatus::Completed);
    }

    /**
     * Scope for listings that have been successfully geocoded.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeGeocoded(Builder $query): Builder
    {
        return $query->where('geocode_status', 'success');
    }

    /**
     * Scope for listings pending geocoding.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopePendingGeocoding(Builder $query): Builder
    {
        return $query->whereNull('geocode_status');
    }
}
