<?php

namespace App\Livewire\Properties;

use App\Models\Property;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Property $property;

    public function mount(Property $property): void
    {
        $this->property = $property->load([
            'listings.platform',
            'listings.agent',
            'listings.agency',
        ]);
    }

    /**
     * Get images from all listings combined.
     *
     * @return array<string>
     */
    #[Computed]
    public function images(): array
    {
        return $this->property->listings
            ->flatMap(fn ($listing) => collect($listing->raw_data['images'] ?? [])
                ->map(fn (array|string $img): string => is_array($img) ? $img['url'] : $img)
            )
            ->filter(fn (string $url): bool => ! str_contains($url, '.svg')
                && ! str_contains($url, 'placeholder')
                && ! str_contains($url, 'icon')
                && preg_match('/\.(jpg|jpeg|png|webp)/i', $url)
            )
            ->unique()
            ->values()
            ->take(20)
            ->toArray();
    }

    /**
     * Get all operations (prices) from all listings.
     *
     * @return array<array{type: string, price: float, currency: string, platform: string, listing_id: int}>
     */
    #[Computed]
    public function allPrices(): array
    {
        return $this->property->listings
            ->flatMap(function ($listing) {
                return collect($listing->raw_data['operations'] ?? [])
                    ->map(fn ($op) => [
                        'type' => $op['type'] ?? 'unknown',
                        'price' => (float) ($op['price'] ?? 0),
                        'currency' => $op['currency'] ?? 'MXN',
                        'platform' => $listing->platform->name,
                        'listing_id' => $listing->id,
                    ]);
            })
            ->filter(fn ($op) => $op['price'] > 0)
            ->sortBy('price')
            ->values()
            ->toArray();
    }

    public function render(): View
    {
        return view('livewire.properties.show')
            ->title($this->property->address ?? 'Property Details');
    }
}
