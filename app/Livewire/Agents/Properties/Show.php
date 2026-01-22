<?php

namespace App\Livewire\Agents\Properties;

use App\Models\Listing;
use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.agent')]
class Show extends Component
{
    public Property $property;

    /** @var array<int> Collection of property IDs (UI mockup state) */
    public array $collection = [];

    public function mount(Property $property): void
    {
        $this->property = $property->load([
            'listings.platform',
            'listings.publisher',
            'publishers',
        ]);
    }

    /**
     * Get the primary listing (most recently scraped).
     */
    #[Computed]
    public function primaryListing(): ?Listing
    {
        return $this->property->listings
            ->sortByDesc('scraped_at')
            ->first();
    }

    /**
     * Get images from all listings (combined and deduplicated).
     *
     * @return array<string>
     */
    #[Computed]
    public function images(): array
    {
        $seen = [];
        $combined = [];

        foreach ($this->property->listings as $listing) {
            $images = collect($listing->raw_data['images'] ?? [])
                ->map(fn (array|string $img): string => is_array($img) ? $img['url'] : $img)
                ->filter(fn (string $url): bool => ! str_contains($url, '.svg')
                    && ! str_contains($url, 'placeholder')
                    && ! str_contains($url, 'icon')
                    && preg_match('/\.(jpg|jpeg|png|webp)/i', $url)
                )
                ->toArray();

            foreach ($images as $url) {
                $normalizedUrl = preg_replace('/\/\d+x\d+\//', '/', $url);
                $normalizedUrl = preg_replace('/\?.*$/', '', $normalizedUrl);

                if (! isset($seen[$normalizedUrl])) {
                    $seen[$normalizedUrl] = true;
                    $combined[] = $url;
                }
            }
        }

        return array_slice($combined, 0, 30);
    }

    /**
     * Get the primary price for display.
     *
     * @return array{type: string, price: float, currency: string, maintenance_fee: float|null}|null
     */
    #[Computed]
    public function primaryPrice(): ?array
    {
        foreach ($this->property->listings as $listing) {
            $operations = $listing->raw_data['operations'] ?? [];
            foreach ($operations as $op) {
                if (($op['price'] ?? 0) > 0) {
                    return [
                        'type' => $op['type'] ?? 'unknown',
                        'price' => (float) $op['price'],
                        'currency' => $op['currency'] ?? 'MXN',
                        'maintenance_fee' => $op['maintenance_fee'] ?? null,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get description - prefer AI property description, then raw.
     */
    #[Computed]
    public function description(): ?string
    {
        if ($this->property->description) {
            return $this->property->description;
        }

        return $this->primaryListing?->raw_data['description'] ?? null;
    }

    /**
     * Get amenities formatted for display.
     *
     * @return array<string>
     */
    #[Computed]
    public function amenities(): array
    {
        return $this->property->amenities ?? [];
    }

    /**
     * Get unique publishers for this property.
     *
     * @return Collection<int, \App\Models\Publisher>
     */
    #[Computed]
    public function publishers(): Collection
    {
        return $this->property->publishers;
    }

    public function toggleCollection(): void
    {
        if (in_array($this->property->id, $this->collection)) {
            $this->collection = array_values(array_filter(
                $this->collection,
                fn ($id) => $id !== $this->property->id
            ));
        } else {
            $this->collection[] = $this->property->id;
        }
    }

    public function isInCollection(): bool
    {
        return in_array($this->property->id, $this->collection);
    }

    public function render(): View
    {
        return view('livewire.agents.properties.show')
            ->title($this->property->address ?? 'Propiedad');
    }
}
