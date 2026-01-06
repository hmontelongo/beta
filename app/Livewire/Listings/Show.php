<?php

namespace App\Livewire\Listings;

use App\Models\Listing;
use Illuminate\View\View;
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

    public function render(): View
    {
        return view('livewire.listings.show');
    }
}
