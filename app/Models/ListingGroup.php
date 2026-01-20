<?php

namespace App\Models;

use App\Enums\ListingGroupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListingGroup extends Model
{
    /** @use HasFactory<\Database\Factories\ListingGroupFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ListingGroupStatus::class,
            'match_score' => 'decimal:2',
            'ai_analysis' => 'array',
            'ai_processed_at' => 'datetime',
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
     * The existing property this group potentially matches against.
     * Used when a new listing matches a listing that's already in a completed group.
     *
     * @return BelongsTo<Property, $this>
     */
    public function matchedProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'matched_property_id');
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'listing_group_id');
    }

    /**
     * Get the primary listing for this group.
     */
    public function primaryListing(): ?Listing
    {
        return $this->listings()->where('is_primary_in_group', true)->first();
    }

    /**
     * Check if this group has multiple listings (is a match group).
     * Uses withCount('listings') for efficiency when available.
     */
    public function isMatchGroup(): bool
    {
        // Use already-loaded count if available (from withCount)
        if (isset($this->attributes['listings_count'])) {
            return $this->attributes['listings_count'] > 1;
        }

        return $this->listings()->count() > 1;
    }

    /**
     * Scope for groups pending human review.
     *
     * @param  Builder<ListingGroup>  $query
     * @return Builder<ListingGroup>
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', ListingGroupStatus::PendingReview);
    }

    /**
     * Scope for groups ready for AI processing.
     *
     * @param  Builder<ListingGroup>  $query
     * @return Builder<ListingGroup>
     */
    public function scopePendingAi(Builder $query): Builder
    {
        return $query->where('status', ListingGroupStatus::PendingAi);
    }

    /**
     * Scope for groups currently being processed by AI.
     *
     * @param  Builder<ListingGroup>  $query
     * @return Builder<ListingGroup>
     */
    public function scopeProcessingAi(Builder $query): Builder
    {
        return $query->where('status', ListingGroupStatus::ProcessingAi);
    }

    /**
     * Scope for groups belonging to a specific property.
     *
     * @param  Builder<ListingGroup>  $query
     * @return Builder<ListingGroup>
     */
    public function scopeForProperty(Builder $query, Property $property): Builder
    {
        return $query->where('property_id', $property->id);
    }

    /**
     * Approve this group for AI processing.
     */
    public function approve(): void
    {
        $this->update(['status' => ListingGroupStatus::PendingAi]);
    }

    /**
     * Reject this group and return listings to pending dedup.
     */
    public function reject(?string $reason = null): void
    {
        $this->update([
            'status' => ListingGroupStatus::Rejected,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Mark as processing by AI.
     */
    public function markAsProcessingAi(): void
    {
        $this->update(['status' => ListingGroupStatus::ProcessingAi]);
    }

    /**
     * Mark as completed after property creation.
     *
     * @param  array<string, mixed>  $aiAnalysis
     */
    public function markAsCompleted(Property $property, array $aiAnalysis): void
    {
        $this->update([
            'status' => ListingGroupStatus::Completed,
            'property_id' => $property->id,
            'ai_analysis' => $aiAnalysis,
            'ai_processed_at' => now(),
        ]);
    }
}
