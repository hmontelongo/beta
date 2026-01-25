<?php

namespace App\Livewire\Agents\Properties\Upload;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.agent')]
#[Title('Agregar fotos')]
class Photos extends Component
{
    use WithFileUploads;

    /**
     * @var array<TemporaryUploadedFile>
     */
    #[Validate(['photos.*' => 'image|max:10240'])]
    public array $photos = [];

    public int $coverIndex = 0;

    public function mount(): void
    {
        // Check if we have extracted data (required step)
        if (! session('property_upload.extracted_data')) {
            $this->redirectRoute('agents.properties.upload.describe', navigate: true);
        }
    }

    public function updatedPhotos(): void
    {
        $this->validateOnly('photos.*');
    }

    public function removePhoto(int $index): void
    {
        if (isset($this->photos[$index])) {
            $this->photos[$index]->delete();
            unset($this->photos[$index]);
            $this->photos = array_values($this->photos);

            // Adjust cover index if needed
            if ($this->coverIndex >= count($this->photos)) {
                $this->coverIndex = max(0, count($this->photos) - 1);
            }
        }
    }

    public function setCover(int $index): void
    {
        if (isset($this->photos[$index])) {
            $this->coverIndex = $index;
        }
    }

    public function reorder(array $order): void
    {
        // Get the filename of the current cover photo to track it
        $coverFilename = isset($this->photos[$this->coverIndex])
            ? $this->photos[$this->coverIndex]->getFilename()
            : null;

        // Reorder photos based on the new order
        $reordered = [];
        foreach ($order as $index) {
            if (isset($this->photos[$index])) {
                $reordered[] = $this->photos[$index];
            }
        }

        $this->photos = $reordered;

        // Update cover index to match new position of the cover photo
        if ($coverFilename) {
            foreach ($this->photos as $i => $photo) {
                if ($photo->getFilename() === $coverFilename) {
                    $this->coverIndex = $i;

                    return;
                }
            }
        }

        // Fallback: if cover photo not found, set to first photo
        $this->coverIndex = 0;
    }

    public function back(): void
    {
        $this->redirectRoute('agents.properties.upload.review', navigate: true);
    }

    public function skip(): void
    {
        session(['property_upload.photos' => []]);
        session(['property_upload.cover_index' => 0]);

        $this->redirectRoute('agents.properties.upload.sharing', navigate: true);
    }

    public function continue(): void
    {
        // Store photo paths temporarily (will be moved to permanent storage on final save)
        $photoPaths = [];
        foreach ($this->photos as $photo) {
            $photoPaths[] = $photo->store('temp-property-uploads', 'local');
        }

        session(['property_upload.photos' => $photoPaths]);
        session(['property_upload.cover_index' => $this->coverIndex]);

        $this->redirectRoute('agents.properties.upload.sharing', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.agents.properties.upload.photos');
    }
}
