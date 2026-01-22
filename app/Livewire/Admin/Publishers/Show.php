<?php

namespace App\Livewire\Admin\Publishers;

use App\Models\Publisher;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use WithPagination;

    public Publisher $publisher;

    public function mount(Publisher $publisher): void
    {
        $this->publisher = $publisher;
    }

    /**
     * @return array{listings: int, properties: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'listings' => $this->publisher->listings()->count(),
            'properties' => $this->publisher->properties()->count(),
        ];
    }

    /**
     * Get platform profile details.
     *
     * @return array<string, array{id: string|null, url: string|null, logo: string|null, scraped_at: string|null}>
     */
    #[Computed]
    public function platformProfiles(): array
    {
        return $this->publisher->platform_profiles ?? [];
    }

    public function render(): View
    {
        $properties = $this->publisher->properties()
            ->withCount('listings')
            ->latest()
            ->paginate(10);

        return view('livewire.admin.publishers.show', [
            'properties' => $properties,
        ])->title($this->publisher->name);
    }
}
