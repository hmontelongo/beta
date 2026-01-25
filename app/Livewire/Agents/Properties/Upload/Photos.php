<?php

namespace App\Livewire\Agents\Properties\Upload;

use Illuminate\Support\Facades\Storage;
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

    /**
     * Paths of previously uploaded photos stored in session.
     *
     * @var array<string>
     */
    public array $savedPhotoPaths = [];

    public function mount(): void
    {
        // Check if we have extracted data (required step)
        if (! session('property_upload.extracted_data')) {
            $this->redirectRoute('agents.properties.upload.describe', navigate: true);

            return;
        }

        // Restore previously uploaded photos from session (back navigation)
        $this->savedPhotoPaths = session('property_upload.photos', []);
        $this->coverIndex = session('property_upload.cover_index', 0);
    }

    public function updatedPhotos(): void
    {
        $this->validateOnly('photos.*');

        // Immediately persist new uploads to simplify reordering
        foreach ($this->photos as $photo) {
            $this->savedPhotoPaths[] = $photo->store('temp-property-uploads', 'local');
        }

        // Clear the temporary uploads array
        $this->photos = [];

        // Update session with new paths
        session(['property_upload.photos' => $this->savedPhotoPaths]);
    }

    public function removePhoto(int $index): void
    {
        if (isset($this->savedPhotoPaths[$index])) {
            Storage::disk('local')->delete($this->savedPhotoPaths[$index]);
            unset($this->savedPhotoPaths[$index]);
            $this->savedPhotoPaths = array_values($this->savedPhotoPaths);

            // Adjust cover index if needed
            $count = count($this->savedPhotoPaths);
            if ($this->coverIndex >= $count) {
                $this->coverIndex = max(0, $count - 1);
            }

            session(['property_upload.photos' => $this->savedPhotoPaths]);
            session(['property_upload.cover_index' => $this->coverIndex]);
        }
    }

    public function setCover(int $index): void
    {
        if ($index >= 0 && $index < count($this->savedPhotoPaths)) {
            $this->coverIndex = $index;
            session(['property_upload.cover_index' => $this->coverIndex]);
        }
    }

    /**
     * Reorder a photo from one position to another.
     */
    public function reorderPhoto(int $fromIndex, int $toIndex): void
    {
        $count = count($this->savedPhotoPaths);

        // Validate indices
        if ($fromIndex < 0 || $fromIndex >= $count || $toIndex < 0 || $toIndex >= $count) {
            return;
        }

        // Perform the reorder
        $item = $this->savedPhotoPaths[$fromIndex];
        array_splice($this->savedPhotoPaths, $fromIndex, 1);
        array_splice($this->savedPhotoPaths, $toIndex, 0, [$item]);

        // Update cover index to follow the photo that was marked as cover
        if ($this->coverIndex === $fromIndex) {
            $this->coverIndex = $toIndex;
        } elseif ($fromIndex < $this->coverIndex && $toIndex >= $this->coverIndex) {
            $this->coverIndex--;
        } elseif ($fromIndex > $this->coverIndex && $toIndex <= $this->coverIndex) {
            $this->coverIndex++;
        }

        // Update session with new order
        session(['property_upload.photos' => $this->savedPhotoPaths]);
        session(['property_upload.cover_index' => $this->coverIndex]);
    }

    public function back(): void
    {
        $this->redirectRoute('agents.properties.upload.review', navigate: true);
    }

    public function skip(): void
    {
        // Clear photos when skipping
        foreach ($this->savedPhotoPaths as $path) {
            Storage::disk('local')->delete($path);
        }
        session(['property_upload.photos' => []]);
        session(['property_upload.cover_index' => 0]);

        $this->redirectRoute('agents.properties.upload.sharing', navigate: true);
    }

    public function continue(): void
    {
        $this->redirectRoute('agents.properties.upload.sharing', navigate: true);
    }

    /**
     * Get total count of all photos.
     */
    public function getTotalPhotoCount(): int
    {
        return count($this->savedPhotoPaths);
    }

    /**
     * Get base64 data URL for a saved photo.
     */
    public function getSavedPhotoUrl(string $path): string
    {
        if (! Storage::disk('local')->exists($path)) {
            return '';
        }

        $contents = Storage::disk('local')->get($path);
        $mimeType = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    public function render(): View
    {
        return view('livewire.agents.properties.upload.photos');
    }
}
