<?php

namespace App\Livewire\Publishers;

use App\Enums\PublisherType;
use App\Models\Publisher;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Publishers')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $sortBy = 'name';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'type']);
        $this->resetPage();
    }

    /**
     * @return array{total: int, individual: int, agency: int, developer: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Publisher::count(),
            'individual' => Publisher::where('type', PublisherType::Individual)->count(),
            'agency' => Publisher::where('type', PublisherType::Agency)->count(),
            'developer' => Publisher::where('type', PublisherType::Developer)->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Publisher>
     */
    protected function buildQuery()
    {
        return Publisher::query()
            ->withCount(['listings', 'properties'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->type, fn ($query) => $query->where('type', $this->type))
            ->when($this->sortBy === 'name', fn ($query) => $query->orderBy('name'))
            ->when($this->sortBy === 'newest', fn ($query) => $query->latest())
            ->when($this->sortBy === 'most_properties', fn ($query) => $query->orderByDesc('properties_count'))
            ->when($this->sortBy === 'most_listings', fn ($query) => $query->orderByDesc('listings_count'));
    }

    public function render(): View
    {
        $publishers = $this->buildQuery()->paginate(20);

        return view('livewire.publishers.index', [
            'publishers' => $publishers,
            'publisherTypes' => PublisherType::cases(),
        ]);
    }
}
