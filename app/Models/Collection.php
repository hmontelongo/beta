<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Collection extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionFactory> */
    use HasFactory;

    /** Default name for draft/unsaved collections */
    public const DRAFT_NAME = 'Nueva coleccion';

    protected $fillable = [
        'user_id',
        'client_id',
        'name',
        'description',
        'client_name',
        'client_whatsapp',
        'share_token',
        'is_public',
        'shared_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'shared_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Collection $collection): void {
            if (empty($collection->share_token)) {
                $collection->share_token = Str::random(16);
            }
        });
    }

    /**
     * @return BelongsTo<User, Collection>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Client, Collection>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsToMany<Property>
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<CollectionView>
     */
    public function views(): HasMany
    {
        return $this->hasMany(CollectionView::class);
    }

    /**
     * Get total view count for this collection.
     */
    public function getViewCountAttribute(): int
    {
        return $this->views_count ?? $this->views()->count();
    }

    /**
     * Get when this collection was last viewed.
     * Use withMax('views', 'viewed_at') when eager loading to avoid N+1.
     */
    public function getLastViewedAtAttribute(): ?Carbon
    {
        // Use eager loaded aggregate if available
        if (array_key_exists('views_max_viewed_at', $this->attributes)) {
            return $this->attributes['views_max_viewed_at'] ? Carbon::parse($this->attributes['views_max_viewed_at']) : null;
        }

        return $this->views()->latest('viewed_at')->value('viewed_at');
    }

    /**
     * Get the derived status of the collection.
     * Simplified to 2 states: active (not yet shared) or shared.
     */
    public function getStatusAttribute(): string
    {
        return $this->shared_at ? 'shared' : 'active';
    }

    /**
     * Get the human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->shared_at ? 'Compartida' : 'En proceso';
    }

    /**
     * Get the Flux badge color for status.
     */
    public function getStatusColorAttribute(): string
    {
        return $this->shared_at ? 'green' : 'blue';
    }

    /**
     * Mark collection as shared. Sets shared_at and is_public, then refreshes model.
     */
    public function markAsShared(): void
    {
        if (! $this->shared_at) {
            $this->update([
                'shared_at' => now(),
                'is_public' => true,
            ]);
            $this->refresh();
        }
    }

    /**
     * Get client name from relationship or legacy field.
     */
    public function getClientNameDisplayAttribute(): ?string
    {
        return $this->client?->name ?? $this->client_name;
    }

    /**
     * Get client WhatsApp from relationship or legacy field.
     */
    public function getClientWhatsappDisplayAttribute(): ?string
    {
        return $this->client?->whatsapp ?? $this->client_whatsapp;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAccessible(): bool
    {
        return $this->is_public && ! $this->isExpired();
    }

    public function getShareUrl(): string
    {
        return route('collections.public.show', ['collection' => $this->share_token]);
    }

    public function getWhatsAppShareUrl(): string
    {
        $message = "Mira esta coleccion de propiedades: {$this->name}\n{$this->getShareUrl()}";
        $whatsapp = $this->client_whatsapp_display;
        $phone = $whatsapp ? preg_replace('/[^0-9]/', '', $whatsapp) : '';

        return "https://wa.me/{$phone}?text=".urlencode($message);
    }

    /**
     * Check if this collection is a draft (unsaved).
     */
    public function isDraft(): bool
    {
        return $this->name === self::DRAFT_NAME;
    }
}
