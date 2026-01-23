<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * Get the derived status of the collection.
     */
    public function getStatusAttribute(): string
    {
        if ($this->isDraft()) {
            return 'draft';
        }

        if ($this->shared_at) {
            return 'shared';
        }

        // Use properties_count if available (from withCount), otherwise check relation or query
        $propertyCount = $this->properties_count
            ?? ($this->relationLoaded('properties') ? $this->properties->count() : $this->properties()->count());

        if ($this->is_public && $propertyCount > 0) {
            return 'ready';
        }

        return 'active';
    }

    /**
     * Get the human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Borrador',
            'active' => 'En proceso',
            'ready' => 'Lista',
            'shared' => 'Compartida',
            default => 'Desconocido',
        };
    }

    /**
     * Get the CSS classes for status badge styling.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
            'active' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'ready' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'shared' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
        };
    }

    /**
     * Get the tooltip text for the status badge.
     */
    public function getStatusTooltipAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Coleccion sin nombre',
            'active' => 'Agregando propiedades',
            'ready' => 'Lista para compartir',
            'shared' => 'Enviada al cliente',
            default => '',
        };
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
