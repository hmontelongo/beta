<?php

namespace App\Livewire\Agents\Properties\Upload;

use App\Models\Property;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.agent')]
#[Title('Propiedad publicada')]
class Complete extends Component
{
    public ?int $propertyId = null;

    public function mount(): void
    {
        $this->propertyId = session('property_upload.completed_id');

        if (! $this->propertyId) {
            $this->redirectRoute('agents.properties.upload.describe', navigate: true);
        }

        // Clear the completed ID from session after mounting
        session()->forget('property_upload.completed_id');
    }

    #[Computed]
    public function property(): ?Property
    {
        if (! $this->propertyId) {
            return null;
        }

        return Property::with(['propertyImages'])->find($this->propertyId);
    }

    public function viewProperty(): void
    {
        if ($this->property) {
            $this->redirectRoute('agents.properties.show', $this->property, navigate: true);
        }
    }

    public function addAnother(): void
    {
        $this->redirectRoute('agents.properties.upload.describe', navigate: true);
    }

    public function myProperties(): void
    {
        $this->redirectRoute('agents.properties.index', navigate: true);
    }

    public function shareViaWhatsApp(): void
    {
        if (! $this->property) {
            return;
        }

        $url = route('agents.properties.show', $this->property);
        $message = "Mira esta propiedad: {$this->property->location_display}\n{$url}";

        $this->dispatch('open-url', url: 'https://wa.me/?text='.urlencode($message));
    }

    public function render(): View
    {
        return view('livewire.agents.properties.upload.complete');
    }
}
