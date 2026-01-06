<?php

namespace App\Livewire\Listings;

use App\Models\Listing;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Listings')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $platform = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPlatform(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $listings = Listing::query()
            ->with(['platform', 'discoveredListing'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('original_url', 'like', "%{$this->search}%")
                        ->orWhere('external_id', 'like', "%{$this->search}%")
                        ->orWhereRaw("JSON_EXTRACT(raw_data, '$.title') LIKE ?", ["%{$this->search}%"]);
                });
            })
            ->when($this->platform, function ($query) {
                $query->where('platform_id', $this->platform);
            })
            ->latest('scraped_at')
            ->paginate(20);

        return view('livewire.listings.index', [
            'listings' => $listings,
            'platforms' => \App\Models\Platform::orderBy('name')->get(),
        ]);
    }
}
