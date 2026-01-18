<?php

namespace App\Models;

use App\Enums\RunFrequency;
use App\Enums\ScrapeRunStatus;
use Illuminate\Database\Eloquent\Builder;
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
            'run_frequency' => RunFrequency::class,
            'next_run_at' => 'datetime',
            'auto_enabled' => 'boolean',
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

    /**
     * Scope for queries that are due to run.
     *
     * @param  Builder<SearchQuery>  $query
     * @return Builder<SearchQuery>
     */
    public function scopeDueForRun(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('auto_enabled', true)
            ->where('run_frequency', '!=', RunFrequency::None)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            });
    }

    /**
     * Check if this query is due for a scheduled run.
     */
    public function isDueForRun(): bool
    {
        if (! $this->is_active || ! $this->auto_enabled) {
            return false;
        }

        if ($this->run_frequency === RunFrequency::None) {
            return false;
        }

        if ($this->next_run_at === null) {
            return true;
        }

        return $this->next_run_at->isPast();
    }

    /**
     * Check if this query has an active run in progress.
     */
    public function hasActiveRun(): bool
    {
        return $this->activeRun()->exists();
    }

    /**
     * Update the next_run_at timestamp based on the frequency.
     */
    public function scheduleNextRun(): void
    {
        $this->update([
            'next_run_at' => $this->run_frequency->nextRunAt(),
            'last_run_at' => now(),
        ]);
    }

    /**
     * Enable automatic scheduling with the given frequency.
     */
    public function enableScheduling(RunFrequency $frequency): void
    {
        $this->update([
            'run_frequency' => $frequency,
            'auto_enabled' => true,
            'next_run_at' => $frequency->nextRunAt(),
        ]);
    }

    /**
     * Disable automatic scheduling.
     */
    public function disableScheduling(): void
    {
        $this->update([
            'auto_enabled' => false,
            'next_run_at' => null,
        ]);
    }
}
