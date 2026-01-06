<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    /** @use HasFactory<\Database\Factories\PlatformFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    /**
     * @return HasMany<DiscoveredListing, $this>
     */
    public function discoveredListings(): HasMany
    {
        return $this->hasMany(DiscoveredListing::class);
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * @return HasMany<ScrapeJob, $this>
     */
    public function scrapeJobs(): HasMany
    {
        return $this->hasMany(ScrapeJob::class);
    }

    /**
     * @return HasMany<SearchQuery, $this>
     */
    public function searchQueries(): HasMany
    {
        return $this->hasMany(SearchQuery::class);
    }

    /**
     * @return HasMany<ScrapeRun, $this>
     */
    public function scrapeRuns(): HasMany
    {
        return $this->hasMany(ScrapeRun::class);
    }
}
