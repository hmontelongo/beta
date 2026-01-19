<?php

namespace App\Livewire\Properties;

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

    #[Url]
    public string $search = '';

    #[Url]
    public string $city = '';

    #[Url]
    public string $colonia = '';

    #[Url]
    public string $propertyType = '';

    #[Url]
    public ?int $bedroomsMin = null;

    #[Url]
    public ?int $bedroomsMax = null;

    #[Url]
    public string $sortBy = 'newest';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCity(): void
    {
        $this->colonia = '';
        $this->resetPage();
    }

    public function updatedColonia(): void
    {
        $this->resetPage();
    }

    public function updatedPropertyType(): void
    {
        $this->resetPage();
    }

    public function updatedBedroomsMin(): void
    {
        $this->resetPage();
    }

    public function updatedBedroomsMax(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'city', 'colonia', 'propertyType', 'bedroomsMin', 'bedroomsMax']);
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
     * @return array<string>
     */
    #[Computed]
    public function colonias(): array
    {
        return Property::query()
            ->whereNotNull('colonia')
            ->when($this->city, fn ($q) => $q->where('city', $this->city))
            ->distinct()
            ->orderBy('colonia')
            ->pluck('colonia')
            ->toArray();
    }

    /**
     * @return array{total: int, with_listings: int, verified: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Property::count(),
            'with_listings' => Property::where('listings_count', '>', 1)->count(),
            'verified' => Property::where('status', 'verified')->count(),
        ];
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
            ->when($this->colonia, fn ($query) => $query->where('colonia', $this->colonia))
            ->when($this->propertyType, fn ($query) => $query->where('property_type', $this->propertyType))
            ->when($this->bedroomsMin !== null, fn ($query) => $query->where('bedrooms', '>=', $this->bedroomsMin))
            ->when($this->bedroomsMax !== null, fn ($query) => $query->where('bedrooms', '<=', $this->bedroomsMax))
            ->when($this->sortBy === 'newest', fn ($query) => $query->latest())
            ->when($this->sortBy === 'oldest', fn ($query) => $query->oldest())
            ->when($this->sortBy === 'most_listings', fn ($query) => $query->orderByDesc('listings_count'))
            ->when($this->sortBy === 'highest_confidence', fn ($query) => $query->orderByDesc('confidence_score'));
    }

    public function render(): View
    {
        $properties = $this->buildQuery()->paginate(20);

        return view('livewire.properties.index', [
            'properties' => $properties,
            'propertyTypes' => PropertyType::cases(),
        ]);
    }
}
