<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    /**
     * Get total views across all collections for this client.
     */
    protected function totalViews(): Attribute
    {
        return Attribute::get(fn () => $this->collections->sum('view_count'));
    }

    /**
     * Get the most recent activity date for this client.
     * Uses collection updated_at to avoid N+1 queries on views.
     */
    protected function lastActivity(): Attribute
    {
        return Attribute::get(function (): ?Carbon {
            // Use the most recently updated collection (already eager loaded)
            $lastUpdatedAt = $this->collections->max('updated_at');

            return $lastUpdatedAt ?? $this->updated_at;
        });
    }

    /**
     * Get the WhatsApp URL for this client.
     */
    protected function whatsappUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->whatsapp) {
                return null;
            }

            $phone = preg_replace('/[^0-9]/', '', $this->whatsapp);

            return "https://wa.me/{$phone}";
        });
    }
}
