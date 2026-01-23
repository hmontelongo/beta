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
        'name',
        'description',
        'client_name',
        'client_whatsapp',
        'share_token',
        'is_public',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
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
     * @return BelongsToMany<Property>
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
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
        $phone = $this->client_whatsapp
            ? preg_replace('/[^0-9]/', '', $this->client_whatsapp)
            : '';

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
