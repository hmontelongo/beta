<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertySubtype;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'property_type' => PropertyType::class,
            'property_subtype' => PropertySubtype::class,
            'lot_size_m2' => 'decimal:2',
            'built_size_m2' => 'decimal:2',
            'amenities' => 'array',
            'status' => PropertyStatus::class,
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
     * @return HasMany<PropertyConflict, $this>
     */
    public function conflicts(): HasMany
    {
        return $this->hasMany(PropertyConflict::class);
    }

    /**
     * @return HasMany<PropertyVerification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(PropertyVerification::class);
    }
}
