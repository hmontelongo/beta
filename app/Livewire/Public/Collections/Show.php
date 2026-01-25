<?php

namespace App\Livewire\Public\Collections;

use App\Models\Collection;
use App\Services\CollectionPropertyPresenter;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Layout('components.layouts.public')]
class Show extends Component
{
    public Collection $collection;

    /** @var array<int, int> Track selected image index per property ID */
    public array $selectedImages = [];

    public function mount(Collection $collection): void
    {
        if (! $collection->isAccessible()) {
            throw new NotFoundHttpException('Collection not found or not accessible.');
        }

        $this->collection = $collection->load(['properties.listings', 'properties.propertyImages', 'user', 'client']);

        $this->trackView();
    }

    /**
     * Get prepared properties with rich data for display.
     *
     * @return SupportCollection<int, array>
     */
    #[Computed]
    public function properties(): SupportCollection
    {
        $presenter = new CollectionPropertyPresenter;

        return $presenter->prepareProperties($this->collection->properties);
    }

    /**
     * Select an image for a property to display as the main image.
     */
    public function selectImage(int $propertyId, int $index): void
    {
        $this->selectedImages[$propertyId] = $index;
    }

    /**
     * Track this view (one per IP per day to avoid spam).
     */
    private function trackView(): void
    {
        $this->collection->views()->firstOrCreate(
            [
                'ip_address' => request()->ip(),
                'viewed_at' => now()->startOfDay(),
            ],
            [
                'user_agent' => request()->userAgent(),
            ]
        );
    }

    public function render(): View
    {
        return view('livewire.public.collections.show')
            ->title($this->collection->name);
    }
}
