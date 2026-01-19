<?php

namespace App\Models;

use App\Enums\PublisherType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publisher extends Model
{
    /** @use HasFactory<\Database\Factories\PublisherFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PublisherType::class,
            'platform_profiles' => 'array',
        ];
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * @return BelongsToMany<Property, $this>
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)->withTimestamps();
    }

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Publisher::class, 'parent_id');
    }

    /**
     * @return HasMany<Publisher, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Publisher::class, 'parent_id');
    }

    /**
     * Get the platforms this publisher is active on.
     *
     * @return array<string>
     */
    public function getPlatformsAttribute(): array
    {
        return array_keys($this->platform_profiles ?? []);
    }
}
