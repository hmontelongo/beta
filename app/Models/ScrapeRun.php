<?php

namespace App\Models;

use App\Enums\ScrapePhase;
use App\Enums\ScrapeRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapeRun extends Model
{
    /** @use HasFactory<\Database\Factories\ScrapeRunFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScrapeRunStatus::class,
            'phase' => ScrapePhase::class,
            'stats' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getProgressAttribute(): int
    {
        $pagesTotal = $this->stats['pages_total'] ?? 0;

        if ($pagesTotal === 0) {
            return 0;
        }

        return (int) round(($this->stats['pages_done'] ?? 0) / $pagesTotal * 100);
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * @return BelongsTo<SearchQuery, $this>
     */
    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(SearchQuery::class);
    }

    /**
     * @return HasMany<ScrapeJob, $this>
     */
    public function scrapeJobs(): HasMany
    {
        return $this->hasMany(ScrapeJob::class);
    }
}
