<?php

namespace App\Livewire\Listings;

use App\Models\Listing;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Listing $listing;

    public function mount(Listing $listing): void
    {
        $this->listing = $listing->load(['platform', 'discoveredListing']);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function images(): array
    {
        return collect($this->listing->raw_data['images'] ?? [])
            ->map(fn (array|string $img): string => is_array($img) ? $img['url'] : $img)
            ->values()
            ->toArray();
    }

    #[Computed]
    public function formattedWhatsapp(): ?string
    {
        $whatsapp = $this->listing->raw_data['whatsapp'] ?? null;

        if (! $whatsapp) {
            return null;
        }

        return preg_replace('/[^0-9]/', '', $whatsapp);
    }

    public function render(): View
    {
        return view('livewire.listings.show');
    }
}
