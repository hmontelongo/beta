<?php

namespace App\Models;

use App\Enums\AiEnrichmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEnrichment extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AiEnrichmentStatus::class,
            'validated_data' => 'array',
            'extracted_tags' => 'array',
            'address_verification' => 'array',
            'quality_issues' => 'array',
            'confidence_scores' => 'array',
            'ai_response_raw' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * Get the estimated cost of this enrichment in USD.
     */
    public function getEstimatedCostAttribute(): ?float
    {
        if (! $this->input_tokens || ! $this->output_tokens) {
            return null;
        }

        // Claude Sonnet pricing (as of 2024)
        $inputCostPer1k = 0.003;
        $outputCostPer1k = 0.015;

        return ($this->input_tokens / 1000 * $inputCostPer1k)
             + ($this->output_tokens / 1000 * $outputCostPer1k);
    }
}
