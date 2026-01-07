<?php

namespace App\Models;

use App\Enums\ScrapeRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SearchQuery extends Model
{
    /** @use HasFactory<\Database\Factories\SearchQueryFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * @return HasMany<ScrapeRun, $this>
     */
    public function scrapeRuns(): HasMany
    {
        return $this->hasMany(ScrapeRun::class);
    }

    /**
     * @return HasOne<ScrapeRun, $this>
     */
    public function latestRun(): HasOne
    {
        return $this->hasOne(ScrapeRun::class)->latestOfMany();
    }

    /**
     * @return HasOne<ScrapeRun, $this>
     */
    public function activeRun(): HasOne
    {
        return $this->hasOne(ScrapeRun::class)
            ->whereIn('status', [
                ScrapeRunStatus::Pending,
                ScrapeRunStatus::Discovering,
                ScrapeRunStatus::Scraping,
            ])
            ->latestOfMany();
    }
}
