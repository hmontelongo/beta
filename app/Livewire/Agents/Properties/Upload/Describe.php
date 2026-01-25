<?php

namespace App\Livewire\Agents\Properties\Upload;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.agent')]
#[Title('Nueva propiedad')]
class Describe extends Component
{
    #[Validate('required|string|min:20', message: 'Por favor describe tu propiedad con al menos 20 caracteres.')]
    public string $description = '';

    public bool $isProcessing = false;

    public function continue(): void
    {
        $this->validate();

        $this->isProcessing = true;

        // Store description in session for the next step
        session(['property_upload.description' => $this->description]);

        // For now, redirect directly to review (AI extraction will be added later)
        $this->redirectRoute('agents.properties.upload.review', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.agents.properties.upload.describe');
    }
}
