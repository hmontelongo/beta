<?php

namespace App\Models;

use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapeJob extends Model
{
    /** @use HasFactory<\Database\Factories\ScrapeJobFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'job_type' => ScrapeJobType::class,
            'filters' => 'array',
            'status' => ScrapeJobStatus::class,
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<ScrapeRun, $this>
     */
    public function scrapeRun(): BelongsTo
    {
        return $this->belongsTo(ScrapeRun::class);
    }

    /**
     * @return BelongsTo<DiscoveredListing, $this>
     */
    public function discoveredListing(): BelongsTo
    {
        return $this->belongsTo(DiscoveredListing::class);
    }

    /**
     * @return BelongsTo<ScrapeJob, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ScrapeJob::class, 'parent_id');
    }

    /**
     * @return HasMany<ScrapeJob, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ScrapeJob::class, 'parent_id');
    }
}
