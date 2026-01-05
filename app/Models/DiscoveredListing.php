<?php

namespace App\Models;

use App\Enums\DiscoveredListingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiscoveredListing extends Model
{
    /** @use HasFactory<\Database\Factories\DiscoveredListingFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DiscoveredListingStatus::class,
            'last_attempt_at' => 'datetime',
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
     * @return HasOne<Listing, $this>
     */
    public function listing(): HasOne
    {
        return $this->hasOne(Listing::class);
    }

    /**
     * @return HasMany<ScrapeJob, $this>
     */
    public function scrapeJobs(): HasMany
    {
        return $this->hasMany(ScrapeJob::class);
    }
}
