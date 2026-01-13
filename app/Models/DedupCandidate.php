<?php

namespace App\Models;

use App\Enums\DedupCandidateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DedupCandidate extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DedupCandidateStatus::class,
            'distance_meters' => 'decimal:2',
            'coordinate_score' => 'decimal:4',
            'address_score' => 'decimal:4',
            'features_score' => 'decimal:4',
            'overall_score' => 'decimal:4',
            'ai_verified' => 'boolean',
            'ai_response_raw' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listingA(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_a_id');
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listingB(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_b_id');
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function resolvedProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'resolved_property_id');
    }

    /**
     * Check if this candidate needs AI verification.
     */
    public function needsAiVerification(): bool
    {
        return $this->status === DedupCandidateStatus::NeedsReview
            && ! $this->ai_verified;
    }

    /**
     * Check if this candidate is resolved.
     */
    public function isResolved(): bool
    {
        return $this->status->isResolved();
    }

    /**
     * Scope for pending candidates that need AI verification.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<DedupCandidate>  $query
     * @return \Illuminate\Database\Eloquent\Builder<DedupCandidate>
     */
    public function scopeNeedingAiVerification($query)
    {
        return $query->where('status', DedupCandidateStatus::NeedsReview)
            ->where('ai_verified', false);
    }
}
