<?php

namespace App\Livewire\Admin\Properties;

use App\Enums\PropertyType;
use App\Models\Property;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Properties')]
class Index extends Component
{
    use WithPagination;

    // Basic filters (always visible)
    #[Url]
    public string $search = '';

    #[Url]
    public string $city = '';

    #[Url]
    public string $propertyType = '';

    #[Url]
    public string $sortBy = 'newest';

    #[Url]
    public string $sortDir = 'desc';

    // Quick filter from cards
    #[Url]
    public string $quickFilter = '';

    // Advanced filters (in modal)
    #[Url]
    public string $bedrooms = '';

    #[Url]
    public string $bathrooms = '';

    #[Url]
    public bool $hasParking = false;

    #[Url]
    public string $minSize = '';

    #[Url]
    public string $maxSize = '';

    /** @var array<string> */
    #[Url]
    public array $amenities = [];

    // Modal state
    public bool $showFiltersModal = false;

    /**
     * Popular amenities for quick filtering.
     *
     * @var array<string, string>
     */
    public array $popularAmenities = [
        'swimming_pool' => 'Pool',
        'gym' => 'Gym',
        'furnished' => 'Furnished',
        'pet_friendly' => 'Pet Friendly',
        'elevator' => 'Elevator',
        '24_hour_security' => 'Security',
        'air_conditioning' => 'A/C',
        'terrace' => 'Terrace',
        'rooftop' => 'Rooftop',
        'covered_parking' => 'Covered Parking',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCity(): void
    {
        $this->resetPage();
    }

    public function updatedPropertyType(): void
    {
        $this->resetPage();
    }

    public function updatedQuickFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->showFiltersModal = false;
        $this->resetPage();
    }

    public function clearAdvancedFilters(): void
    {
        $this->bedrooms = '';
        $this->bathrooms = '';
        $this->hasParking = false;
        $this->minSize = '';
        $this->maxSize = '';
        $this->amenities = [];
    }

    public function clearAllFilters(): void
    {
        $this->reset([
            'search', 'city', 'propertyType', 'quickFilter',
            'bedrooms', 'bathrooms', 'hasParking', 'minSize', 'maxSize', 'amenities',
        ]);
        $this->resetPage();
    }

    public function removeFilter(string $filter): void
    {
        match ($filter) {
            'search' => $this->search = '',
            'city' => $this->city = '',
            'propertyType' => $this->propertyType = '',
            'quickFilter' => $this->quickFilter = '',
            'bedrooms' => $this->bedrooms = '',
            'bathrooms' => $this->bathrooms = '',
            'hasParking' => $this->hasParking = false,
            'minSize' => $this->minSize = '',
            'maxSize' => $this->maxSize = '',
            default => null,
        };

        // Handle amenity removal
        if (str_starts_with($filter, 'amenity:')) {
            $amenity = str_replace('amenity:', '', $filter);
            $this->amenities = array_values(array_filter($this->amenities, fn ($a) => $a !== $amenity));
        }

        $this->resetPage();
    }

    /**
     * @return array<string>
     */
    #[Computed]
    public function cities(): array
    {
        return Property::query()
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->toArray();
    }

    /**
     * Get filter stats for clickable filter cards.
     *
     * @return array{total: int, multi_listing: int, needs_reanalysis: int, with_parking: int, high_confidence: int, low_confidence: int}
     */
    #[Computed]
    public function filterStats(): array
    {
        return [
            'total' => Property::count(),
            'multi_listing' => Property::where('listings_count', '>', 1)->count(),
            'needs_reanalysis' => Property::where('needs_reanalysis', true)->count(),
            'with_parking' => Property::where('parking_spots', '>', 0)->count(),
            'high_confidence' => Property::where('confidence_score', '>=', 80)->count(),
            'low_confidence' => Property::where('confidence_score', '<', 60)->count(),
        ];
    }

    /**
     * Get the count of active advanced filters.
     */
    #[Computed]
    public function advancedFilterCount(): int
    {
        $count = 0;
        if ($this->bedrooms !== '') {
            $count++;
        }
        if ($this->bathrooms !== '') {
            $count++;
        }
        if ($this->hasParking) {
            $count++;
        }
        if ($this->minSize !== '' || $this->maxSize !== '') {
            $count++;
        }
        $count += count($this->amenities);

        return $count;
    }

    /**
     * Get active filters for displaying as chips.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function activeFilters(): array
    {
        $filters = [];

        if ($this->search !== '') {
            $filters['search'] = "Search: {$this->search}";
        }
        if ($this->city !== '') {
            $filters['city'] = "City: {$this->city}";
        }
        if ($this->propertyType !== '') {
            $filters['propertyType'] = 'Type: '.ucfirst($this->propertyType);
        }
        if ($this->quickFilter !== '') {
            $labels = [
                'multi_listing' => 'Multi-Listing',
                'needs_reanalysis' => 'Needs Update',
                'with_parking' => 'With Parking',
                'high_confidence' => 'High Confidence',
                'low_confidence' => 'Low Confidence',
            ];
            $filters['quickFilter'] = $labels[$this->quickFilter] ?? $this->quickFilter;
        }
        if ($this->bedrooms !== '') {
            $filters['bedrooms'] = "{$this->bedrooms}+ Beds";
        }
        if ($this->bathrooms !== '') {
            $filters['bathrooms'] = "{$this->bathrooms}+ Baths";
        }
        if ($this->hasParking) {
            $filters['hasParking'] = 'Has Parking';
        }
        if ($this->minSize !== '' || $this->maxSize !== '') {
            $size = '';
            if ($this->minSize !== '' && $this->maxSize !== '') {
                $size = "{$this->minSize}-{$this->maxSize} m²";
            } elseif ($this->minSize !== '') {
                $size = "{$this->minSize}+ m²";
            } else {
                $size = "≤{$this->maxSize} m²";
            }
            $filters['size'] = $size;
        }
        foreach ($this->amenities as $amenity) {
            $label = $this->popularAmenities[$amenity] ?? ucfirst(str_replace('_', ' ', $amenity));
            $filters["amenity:{$amenity}"] = $label;
        }

        return $filters;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Property>
     */
    protected function buildQuery()
    {
        return Property::query()
            ->withCount('listings')
            ->with(['publishers', 'listings.platform'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('address', 'like', "%{$this->search}%")
                        ->orWhere('colonia', 'like', "%{$this->search}%")
                        ->orWhere('city', 'like', "%{$this->search}%");
                });
            })
            ->when($this->city, fn ($query) => $query->where('city', $this->city))
            ->when($this->propertyType, fn ($query) => $query->where('property_type', $this->propertyType))
            ->when($this->bedrooms !== '', fn ($query) => $query->where('bedrooms', '>=', (int) $this->bedrooms))
            ->when($this->bathrooms !== '', fn ($query) => $query->where('bathrooms', '>=', (int) $this->bathrooms))
            ->when($this->hasParking, fn ($query) => $query->where('parking_spots', '>', 0))
            ->when($this->minSize !== '', fn ($query) => $query->where('built_size_m2', '>=', (int) $this->minSize))
            ->when($this->maxSize !== '', fn ($query) => $query->where('built_size_m2', '<=', (int) $this->maxSize))
            ->when(! empty($this->amenities), function ($query) {
                foreach ($this->amenities as $amenity) {
                    $query->whereJsonContains('amenities', $amenity);
                }
            })
            ->when($this->quickFilter, fn ($query) => $this->applyQuickFilter($query, $this->quickFilter))
            ->when($this->sortBy === 'newest', fn ($query) => $query->orderBy('created_at', $this->sortDir))
            ->when($this->sortBy === 'address', fn ($query) => $query->orderBy('address', $this->sortDir))
            ->when($this->sortBy === 'bedrooms', fn ($query) => $query->orderBy('bedrooms', $this->sortDir))
            ->when($this->sortBy === 'size', fn ($query) => $query->orderBy('built_size_m2', $this->sortDir))
            ->when($this->sortBy === 'listings', fn ($query) => $query->orderBy('listings_count', $this->sortDir))
            ->when($this->sortBy === 'confidence', fn ($query) => $query->orderBy('confidence_score', $this->sortDir));
    }

    /**
     * Apply quick filter to query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Property>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Property>
     */
    protected function applyQuickFilter($query, string $filter)
    {
        return match ($filter) {
            'multi_listing' => $query->where('listings_count', '>', 1),
            'needs_reanalysis' => $query->where('needs_reanalysis', true),
            'with_parking' => $query->where('parking_spots', '>', 0),
            'high_confidence' => $query->where('confidence_score', '>=', 80),
            'low_confidence' => $query->where('confidence_score', '<', 60),
            default => $query,
        };
    }

    public function render(): View
    {
        $properties = $this->buildQuery()->paginate(20);

        return view('livewire.admin.properties.index', [
            'properties' => $properties,
            'propertyTypes' => PropertyType::cases(),
        ]);
    }
}
