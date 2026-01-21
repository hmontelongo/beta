<?php

namespace App\Models;

use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
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
        $stats = $this->computeStats();
        $pagesTotal = $stats['pages_total'] ?? 0;

        if ($pagesTotal === 0) {
            return 0;
        }

        return (int) round(($stats['pages_done'] ?? 0) / $pagesTotal * 100);
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

    /**
     * @return HasMany<DiscoveredListing, $this>
     */
    public function discoveredListings(): HasMany
    {
        return $this->hasMany(DiscoveredListing::class);
    }

    /**
     * Compute accurate stats from actual records.
     * This is the single source of truth for progress tracking.
     *
     * With the scout pattern, pages_total is computed from actual discovery jobs
     * rather than being calculated upfront. This ensures we always have an accurate
     * count even as scouts discover additional pages.
     *
     * @return array{pages_total: int, pages_done: int, pages_failed: int, listings_found: int, listings_scraped: int, listings_failed: int}
     */
    public function computeStats(): array
    {
        // Discovery stats from scrape_jobs
        // pages_total = count of all discovery jobs (grows as scouts find more pages)
        $discoveryStats = $this->scrapeJobs()
            ->where('job_type', ScrapeJobType::Discovery)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed
            ', [ScrapeJobStatus::Completed->value, ScrapeJobStatus::Failed->value])
            ->first();

        // Scraping stats from discovered_listings
        $scrapingStats = $this->discoveredListings()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'scraped' THEN 1 ELSE 0 END) as scraped,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->first();

        return [
            'pages_total' => (int) ($discoveryStats->total ?? 0),
            'pages_done' => (int) ($discoveryStats->done ?? 0),
            'pages_failed' => (int) ($discoveryStats->failed ?? 0),
            'listings_found' => (int) ($scrapingStats->total ?? 0),
            'listings_scraped' => (int) ($scrapingStats->scraped ?? 0),
            'listings_failed' => (int) ($scrapingStats->failed ?? 0),
        ];
    }
}
