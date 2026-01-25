<?php

namespace App\Livewire\Agents\Properties;

use App\Enums\OperationType;
use App\Enums\PropertyType;
use App\Livewire\Concerns\HasActiveCollection;
use App\Livewire\Concerns\ShowsWhatsAppTip;
use App\Models\Collection;
use App\Models\Property;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.agent')]
#[Title('Propiedades')]
class Index extends Component
{
    use HasActiveCollection;
    use ShowsWhatsAppTip;
    use WithPagination;

    #[Url]
    public string $source = '';

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

    public bool $showCollectionPanel = false;

    /** Collection name for save */
    #[Validate('required|string|max:255')]
    public string $saveName = '';

    /**
     * Price presets for sale operations (MXN).
     *
     * @var array<string, array{min: int|null, max: int|null, label: string}>
     */
    protected const SALE_PRICE_PRESETS = [
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
    protected const RENT_PRICE_PRESETS = [
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
        return $this->operationType === 'rent' ? self::RENT_PRICE_PRESETS : self::SALE_PRICE_PRESETS;
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

    public function mount(): void
    {
        $this->initializeActiveCollection();
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

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
            'source', 'search', 'operationType', 'propertyType', 'zones', 'pricePreset',
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
        return $this->getActiveCollectionModel();
    }

    /**
     * Get the share URL for the active collection.
     */
    #[Computed]
    public function collectionShareUrl(): ?string
    {
        return $this->activeCollection?->getShareUrl();
    }

    /**
     * Clear all collection-related computed property caches.
     */
    protected function clearCollectionCaches(): void
    {
        unset($this->collectionPropertyIds);
        unset($this->collectionProperties);
        unset($this->activeCollection);
        unset($this->collectionShareUrl);
        unset($this->suggestedName);
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
    }

    /**
     * Pre-fill the save name when opening the collection panel.
     */
    public function updatedShowCollectionPanel(bool $value): void
    {
        if ($value) {
            $collection = $this->activeCollection;
            if ($collection) {
                $this->saveName = $collection->isDraft() ? $this->suggestedName : $collection->name;
            }
        }
    }

    /**
     * Save collection name (inline edit).
     */
    public function saveCollectionName(): void
    {
        if (trim($this->saveName) === '') {
            return;
        }

        $collection = $this->activeCollection;

        if (! $collection) {
            return;
        }

        $collection->update([
            'name' => $this->saveName,
        ]);

        $this->clearCollectionCaches();

        Flux::toast(
            text: 'Nombre guardado',
            variant: 'success',
        );
    }

    /**
     * Share collection via WhatsApp.
     */
    public function shareViaWhatsApp(): void
    {
        $collection = $this->activeCollection;

        if (! $collection || $collection->properties()->count() === 0) {
            return;
        }

        $this->saveNameIfChanged($collection);
        $collection->markAsShared();

        $this->dispatch('open-url', url: $collection->getWhatsAppShareUrl());

        $this->showWhatsAppTipIfNeeded();
    }

    /**
     * Handle link copied event - mark as shared and show toast.
     * Called after clipboard copy happens in Alpine.
     */
    public function onLinkCopied(): void
    {
        $collection = $this->activeCollection;

        if (! $collection || $collection->properties()->count() === 0) {
            return;
        }

        $this->saveNameIfChanged($collection);
        $collection->markAsShared();

        Flux::toast(
            heading: 'Link copiado',
            text: 'El link ha sido copiado al portapapeles',
            variant: 'success',
        );

        $this->showWhatsAppTipIfNeeded();
    }

    /**
     * Save the collection name if it has been changed.
     */
    private function saveNameIfChanged(Collection $collection): void
    {
        if (trim($this->saveName) !== '' && $this->saveName !== $collection->name) {
            $collection->update(['name' => $this->saveName]);
        }
    }

    /**
     * Save collection and redirect to detail page (legacy for tests).
     */
    public function saveCollection(): void
    {
        $this->validateOnly('saveName');

        $collection = $this->activeCollection;

        if (! $collection) {
            return;
        }

        $collection->update([
            'name' => $this->saveName,
        ]);

        $this->showCollectionPanel = false;

        Flux::toast(
            heading: 'Coleccion guardada',
            text: $this->saveName,
            variant: 'success',
        );

        $this->redirectRoute('agents.collections.show', $collection, navigate: true);
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
     * Count of properties owned by the current user.
     * Cached for 5 minutes to reduce database queries on page navigation.
     */
    #[Computed]
    public function myPropertiesCount(): int
    {
        return Cache::remember(
            'user.'.auth()->id().'.my_properties_count',
            now()->addMinutes(5),
            fn () => Property::native()->ownedBy(auth()->id())->count()
        );
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
            $this->source,
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

        return $collection->properties()->with(['listings', 'propertyImages'])->get();
    }

    /**
     * Generate a smart suggested name based on collection properties.
     */
    #[Computed]
    public function suggestedName(): string
    {
        $properties = $this->collectionProperties;

        if ($properties->isEmpty()) {
            return 'Mi seleccion';
        }

        // Get most common location (colonia)
        $location = $properties->groupBy('colonia')
            ->sortByDesc(fn ($group) => $group->count())
            ->keys()
            ->first();

        // Get operation type from first property
        $firstProperty = $properties->first();
        if ($firstProperty->isNative()) {
            // Native properties have operation_type directly on the model
            $opLabel = $firstProperty->operation_type?->labelEsPlural() ?? 'Ventas';
        } else {
            // Scraped properties get operation type from listings
            $listing = $firstProperty->listings->first();
            $opType = $listing?->operations[0]['type'] ?? null;
            $opLabel = $opType ? OperationType::from($opType)->labelEsPlural() : 'Ventas';
        }

        return $location ? "{$opLabel} en {$location}" : 'Mi seleccion';
    }

    /**
     * @return Builder<Property>
     */
    protected function buildQuery(): Builder
    {
        $minPrice = $this->minPrice !== '' ? (int) $this->minPrice : null;
        $maxPrice = $this->maxPrice !== '' ? (int) $this->maxPrice : null;

        return Property::query()
            ->with(['listings.platform', 'propertyImages'])
            ->when($this->source === 'mine', fn ($q) => $q->native()->ownedBy(auth()->id()))
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
            // Operation type filter: when no price range, filter by type only.
            // When price range is set, filterByPriceRange handles both price AND operation type
            // because scraped properties store prices per-operation in listings JSON.
            ->when($this->operationType && $minPrice === null && $maxPrice === null, fn ($query) => $query->filterByOperationType($this->operationType))
            ->when($minPrice !== null || $maxPrice !== null, fn ($query) => $query->filterByPriceRange($minPrice, $maxPrice, $this->operationType ?: null))
            // Sorting
            ->when($this->sortBy === 'newest', fn ($query) => $query->orderBy('created_at', 'desc'))
            ->when($this->sortBy === 'oldest', fn ($query) => $query->orderBy('created_at', 'asc'))
            ->when($this->sortBy === 'price_low', fn ($query) => $query->orderByPrice('asc'))
            ->when($this->sortBy === 'price_high', fn ($query) => $query->orderByPrice('desc'))
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
