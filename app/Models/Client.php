<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'name',
        'whatsapp',
        'email',
        'notes',
    ];

    /**
     * Get the agent (user) who owns this client.
     *
     * @return BelongsTo<User, Client>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the collections for this client.
     *
     * @return HasMany<Collection>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }
}
