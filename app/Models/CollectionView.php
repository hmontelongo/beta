<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'collection_id',
        'ip_address',
        'user_agent',
        'viewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Collection, CollectionView>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
