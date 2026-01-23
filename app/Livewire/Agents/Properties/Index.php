<?php

namespace App\Livewire\Agents\Properties;

use App\Enums\PropertyType;
use App\Models\Collection;
use App\Models\Property;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.agent')]
#[Title('Propiedades')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $operationType = '';

    #[Url]
    public string $propertyType = '';

    /** @var array<string> */
    #[Url]
    public array $zones = [];

    #[Url]
    public string $pricePreset = '';

    #[Url]
    public string $minPrice = '';

    #[Url]
    public string $maxPrice = '';

    #[Url]
    public string $bedrooms = '';

    #[Url]
    public string $bathrooms = '';

    #[Url]
    public string $minSize = '';

    #[Url]
    public string $maxSize = '';

    #[Url]
    public string $parking = '';

    /** @var array<string> */
    #[Url]
    public array $amenities = [];

    #[Url]
    public string $sortBy = 'newest';

    public bool $showFiltersModal = false;

    /** Active collection ID (persisted in database) */
    public ?int $activeCollectionId = null;

    public bool $showCollectionPanel = false;

    /** Show only selected properties in the grid */
    public bool $showSelectedOnly = false;

    /** Name for the new collection being created */
    public string $collectionName = '';

    /** Client name for the collection */
    public string $clientName = '';

    /** Client WhatsApp number for instant sharing */
    public string $clientWhatsapp = '';

    /** Whether to save collection as public (shareable) */
    public bool $saveAsPublic = true;

    /** Track if editing an existing saved collection */
    public bool $isEditingCollection = false;

    /**
     * Price presets for sale operations (MXN).
     *
     * @var array<string, array{min: int|null, max: int|null, label: string}>
     */
    public array $salePricePresets = [
        '2m' => ['min' => null, 'max' => 2000000, 'label' => '< $2M'],
        '2m-4m' => ['min' => 2000000, 'max' => 4000000, 'label' => '$2M - $4M'],
        '4m-8m' => ['min' => 4000000, 'max' => 8000000, 'label' => '$4M - $8M'],
        '8m-15m' => ['min' => 8000000, 'max' => 15000000, 'label' => '$8M - $15M'],
        '15m' => ['min' => 15000000, 'max' => null, 'label' => '$15M+'],
    ];

    /**
     * Price presets for rent operations (MXN/month).
     *
     * @var array<string, array{min: int|null, max: int|null, label: string}>
     */
    public array $rentPricePresets = [
        '15k' => ['min' => null, 'max' => 15000, 'label' => '< $15k'],
        '15k-25k' => ['min' => 15000, 'max' => 25000, 'label' => '$15k - $25k'],
        '25k-40k' => ['min' => 25000, 'max' => 40000, 'label' => '$25k - $40k'],
        '40k-80k' => ['min' => 40000, 'max' => 80000, 'label' => '$40k - $80k'],
        '80k' => ['min' => 80000, 'max' => null, 'label' => '$80k+'],
    ];

    /**
     * Get the active price presets based on operation type.
     *
     * @return array<string, array{min: int|null, max: int|null, label: string}>
     */
    #[Computed]
    public function pricePresets(): array
    {
        return $this->operationType === 'rent' ? $this->rentPricePresets : $this->salePricePresets;
    }

    /**
     * Available amenities with Mexican terms.
     *
     * @var array<string, string>
     */
    public array $availableAmenities = [
        'swimming_pool' => 'Alberca',
        '24_hour_security' => 'Seguridad 24h',
        'gated_community' => 'Coto cerrado',
        'covered_parking' => 'Estacionamiento techado',
        'roof_garden' => 'Roof garden',
        'terrace' => 'Terraza',
        'furnished' => 'Amueblado',
        'pet_friendly' => 'Mascotas permitidas',
        'gym' => 'Gimnasio',
        'elevator' => 'Elevador',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOperationType(): void
    {
        // Reset price preset when operation type changes (different price ranges)
        $this->pricePreset = '';
        $this->minPrice = '';
        $this->maxPrice = '';
        $this->resetPage();
    }

    public function updatedPropertyType(): void
    {
        $this->resetPage();
    }

    public function updatedZones(): void
    {
        $this->resetPage();
    }

    public function updatedPricePreset(): void
    {
        $presets = $this->pricePresets;
        if ($this->pricePreset && isset($presets[$this->pricePreset])) {
            $preset = $presets[$this->pricePreset];
            $this->minPrice = $preset['min'] ? (string) $preset['min'] : '';
            $this->maxPrice = $preset['max'] ? (string) $preset['max'] : '';
        } else {
            $this->minPrice = '';
            $this->maxPrice = '';
        }
        $this->resetPage();
    }

    public function updatedMinPrice(): void
    {
        $this->pricePreset = '';
        $this->resetPage();
    }

    public function updatedMaxPrice(): void
    {
        $this->pricePreset = '';
        $this->resetPage();
    }

    public function updatedBedrooms(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->showFiltersModal = false;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search', 'operationType', 'propertyType', 'zones', 'pricePreset',
            'minPrice', 'maxPrice', 'bedrooms', 'bathrooms', 'minSize', 'maxSize',
            'parking', 'amenities',
        ]);
        $this->resetPage();
    }

    /**
     * Get the active collection model.
     */
    #[Computed]
    public function activeCollection(): ?Collection
    {
        if (! $this->activeCollectionId) {
            return null;
        }

        return Collection::find($this->activeCollectionId);
    }

    /**
     * Ensure an active collection exists, creating one if needed.
     */
    protected function ensureActiveCollection(): Collection
    {
        if ($this->activeCollectionId) {
            $collection = Collection::find($this->activeCollectionId);
            if ($collection) {
                return $collection;
            }
        }

        $collection = auth()->user()->collections()->create([
            'name' => Collection::DRAFT_NAME,
        ]);

        $this->activeCollectionId = $collection->id;

        return $collection;
    }

    /**
     * Clear all collection-related computed property caches.
     */
    protected function clearCollectionCaches(): void
    {
        unset($this->collectionPropertyIds);
        unset($this->collectionProperties);
        unset($this->activeCollection);
        unset($this->userCollections);
        unset($this->hasMoreCollections);
    }

    /**
     * Get property IDs in the current collection (for backward compatibility).
     *
     * @return array<int>
     */
    #[Computed]
    public function collectionPropertyIds(): array
    {
        $collection = $this->activeCollection;

        if (! $collection) {
            return [];
        }

        return $collection->properties()->pluck('properties.id')->toArray();
    }

    public function toggleCollection(int $propertyId): void
    {
        $collection = $this->ensureActiveCollection();

        if ($collection->properties()->where('property_id', $propertyId)->exists()) {
            $collection->properties()->detach($propertyId);
        } else {
            $maxPosition = $collection->properties()->max('position') ?? 0;
            $collection->properties()->attach($propertyId, ['position' => $maxPosition + 1]);
        }

        $this->clearCollectionCaches();
    }

    public function removeFromCollection(int $propertyId): void
    {
        $collection = $this->activeCollection;

        if ($collection) {
            $collection->properties()->detach($propertyId);
            $this->clearCollectionCaches();
        }
    }

    public function clearCollection(): void
    {
        $collection = $this->activeCollection;

        if ($collection) {
            $collection->properties()->detach();
            $this->clearCollectionCaches();
        }

        $this->showSelectedOnly = false;
    }

    public function toggleShowSelectedOnly(): void
    {
        $this->showSelectedOnly = ! $this->showSelectedOnly;
        $this->resetPage();
    }

    /**
     * Save the current collection with a name and optional client info.
     */
    public function saveCollection(): void
    {
        $collection = $this->activeCollection;

        if (! $collection || $collection->properties()->count() === 0) {
            return;
        }

        $this->validate([
            'collectionName' => 'required|string|max:255',
            'clientName' => 'nullable|string|max:255',
            'clientWhatsapp' => 'nullable|string|max:20',
        ]);

        $collection->update([
            'name' => $this->collectionName,
            'is_public' => $this->saveAsPublic,
            'client_name' => $this->clientName ?: null,
            'client_whatsapp' => $this->clientWhatsapp ?: null,
        ]);

        $count = $collection->properties()->count();

        Flux::toast(
            heading: 'Coleccion guardada',
            text: "{$this->collectionName} ({$count} propiedades)",
            variant: 'success',
        );

        // Mark as editing existing collection (don't reset activeCollectionId)
        $this->isEditingCollection = true;
        $this->showCollectionPanel = false;

        $this->clearCollectionCaches();
    }

    /**
     * Share collection via WhatsApp.
     */
    public function shareViaWhatsApp(): void
    {
        $collection = $this->activeCollection;

        if (! $collection || $collection->properties()->count() === 0) {
            Flux::toast(
                heading: 'Sin propiedades',
                text: 'Agrega propiedades a la coleccion primero',
                variant: 'warning',
            );

            return;
        }

        // Auto-save if not saved yet
        if ($collection->isDraft()) {
            if (! $this->collectionName) {
                Flux::toast(
                    heading: 'Nombre requerido',
                    text: 'Ingresa un nombre para la coleccion',
                    variant: 'warning',
                );

                return;
            }
            $this->saveCollection();
            $collection->refresh();
        }

        // Ensure collection is public before sharing
        if (! $collection->is_public) {
            $collection->update(['is_public' => true]);
        }

        $this->dispatch('open-url', url: $collection->getWhatsAppShareUrl());
    }

    /**
     * Load an existing collection for editing.
     */
    public function loadCollection(int $collectionId): void
    {
        $collection = auth()->user()->collections()->findOrFail($collectionId);

        $this->activeCollectionId = $collection->id;
        $this->collectionName = $collection->name;
        $this->clientName = $collection->client_name ?? '';
        $this->clientWhatsapp = $collection->client_whatsapp ?? '';
        $this->saveAsPublic = $collection->is_public;
        $this->isEditingCollection = true;

        $this->clearCollectionCaches();

        Flux::toast("Coleccion '{$collection->name}' cargada");
    }

    /**
     * Start a new collection (clear current state).
     */
    public function startNewCollection(): void
    {
        $this->activeCollectionId = null;
        $this->collectionName = '';
        $this->clientName = '';
        $this->clientWhatsapp = '';
        $this->saveAsPublic = true;
        $this->isEditingCollection = false;
        $this->showSelectedOnly = false;

        $this->clearCollectionCaches();

        Flux::toast('Nueva coleccion iniciada');
    }

    /**
     * Get user's saved collections for the selector dropdown.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Collection>
     */
    #[Computed]
    public function userCollections(): \Illuminate\Database\Eloquent\Collection
    {
        return auth()->user()
            ->collections()
            ->where('name', '!=', Collection::DRAFT_NAME)
            ->withCount('properties')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();
    }

    /**
     * Check if user has more collections than displayed in dropdown.
     */
    #[Computed]
    public function hasMoreCollections(): bool
    {
        return auth()->user()
            ->collections()
            ->where('name', '!=', Collection::DRAFT_NAME)
            ->count() > 10;
    }

    public function isInCollection(int $propertyId): bool
    {
        return in_array($propertyId, $this->collectionPropertyIds);
    }

    /**
     * Get zones grouped by city for the multi-select dropdown.
     *
     * @return SupportCollection<string, SupportCollection<int, string>>
     */
    #[Computed]
    public function zonesGroupedByCity(): SupportCollection
    {
        return Property::query()
            ->select('city', 'colonia')
            ->whereNotNull('city')
            ->whereNotNull('colonia')
            ->distinct()
            ->orderBy('city')
            ->orderBy('colonia')
            ->get()
            ->groupBy('city')
            ->map(fn ($items) => $items->pluck('colonia')->unique()->sort()->values());
    }

    /**
     * Get the count of active filters.
     */
    #[Computed]
    public function activeFilterCount(): int
    {
        $count = 0;
        if ($this->minPrice !== '' || $this->maxPrice !== '') {
            $count++;
        }
        if ($this->bedrooms !== '') {
            $count++;
        }
        if ($this->bathrooms !== '') {
            $count++;
        }
        if ($this->minSize !== '' || $this->maxSize !== '') {
            $count++;
        }
        if ($this->parking !== '') {
            $count++;
        }
        $count += count($this->amenities);

        return $count;
    }

    /**
     * Generate a unique key for the property grid based on current filter state.
     * This forces a full re-render of the grid when filters change, enabling animations.
     */
    #[Computed]
    public function gridKey(): string
    {
        return md5(serialize([
            $this->operationType,
            $this->propertyType,
            $this->zones,
            $this->minPrice,
            $this->maxPrice,
            $this->bedrooms,
            $this->bathrooms,
            $this->minSize,
            $this->maxSize,
            $this->parking,
            $this->amenities,
            $this->sortBy,
            $this->search,
            $this->showSelectedOnly,
            $this->activeCollectionId,
        ]));
    }

    /**
     * Get collection properties for the panel.
     *
     * @return SupportCollection<int, Property>
     */
    #[Computed]
    public function collectionProperties(): SupportCollection
    {
        $collection = $this->activeCollection;

        if (! $collection) {
            return collect();
        }

        return $collection->properties()->with(['listings'])->get();
    }

    /**
     * @return Builder<Property>
     */
    protected function buildQuery(): Builder
    {
        $collectionPropertyIds = $this->collectionPropertyIds;

        return Property::query()
            ->with(['listings.platform'])
            ->when($this->showSelectedOnly && ! empty($collectionPropertyIds), function ($query) use ($collectionPropertyIds) {
                $query->whereIn('id', $collectionPropertyIds);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('address', 'like', "%{$this->search}%")
                        ->orWhere('colonia', 'like', "%{$this->search}%")
                        ->orWhere('city', 'like', "%{$this->search}%");
                });
            })
            ->when($this->propertyType, fn ($query) => $query->where('property_type', $this->propertyType))
            ->when(! empty($this->zones), function ($query) {
                $query->where(function ($q) {
                    foreach ($this->zones as $zone) {
                        $q->orWhere('colonia', $zone);
                    }
                });
            })
            ->when($this->bedrooms !== '', fn ($query) => $query->where('bedrooms', '>=', (int) $this->bedrooms))
            ->when($this->bathrooms !== '', fn ($query) => $query->where('bathrooms', '>=', (int) $this->bathrooms))
            ->when($this->parking !== '', fn ($query) => $query->where('parking_spots', '>=', (int) $this->parking))
            ->when($this->minSize !== '', fn ($query) => $query->where('built_size_m2', '>=', (int) $this->minSize))
            ->when($this->maxSize !== '', fn ($query) => $query->where('built_size_m2', '<=', (int) $this->maxSize))
            ->when(! empty($this->amenities), function ($query) {
                foreach ($this->amenities as $amenity) {
                    $query->whereJsonContains('amenities', $amenity);
                }
            })
            ->when($this->operationType || $this->minPrice !== '' || $this->maxPrice !== '', function ($query) {
                $query->whereHas('listings', function ($q) {
                    $q->when($this->operationType, function ($subQ) {
                        $subQ->whereJsonContains('operations', ['type' => $this->operationType]);
                    });
                    $q->when($this->minPrice !== '' || $this->maxPrice !== '', function ($subQ) {
                        $subQ->whereRaw("JSON_EXTRACT(operations, '$[0].price') >= ?", [(int) ($this->minPrice ?: 0)])
                            ->when($this->maxPrice !== '', function ($innerQ) {
                                $innerQ->whereRaw("JSON_EXTRACT(operations, '$[0].price') <= ?", [(int) $this->maxPrice]);
                            });
                    });
                });
            })
            ->when($this->sortBy === 'newest', fn ($query) => $query->orderBy('created_at', 'desc'))
            ->when($this->sortBy === 'oldest', fn ($query) => $query->orderBy('created_at', 'asc'))
            ->when($this->sortBy === 'price_low', fn ($query) => $query->orderByRaw('(SELECT MIN(JSON_EXTRACT(operations, "$[0].price")) FROM listings WHERE listings.property_id = properties.id) ASC'))
            ->when($this->sortBy === 'price_high', fn ($query) => $query->orderByRaw('(SELECT MAX(JSON_EXTRACT(operations, "$[0].price")) FROM listings WHERE listings.property_id = properties.id) DESC'))
            ->when($this->sortBy === 'size', fn ($query) => $query->orderBy('built_size_m2', 'desc'));
    }

    public function render(): View
    {
        $properties = $this->buildQuery()->paginate(12);

        return view('livewire.agents.properties.index', [
            'properties' => $properties,
            'propertyTypes' => PropertyType::cases(),
        ]);
    }
}
