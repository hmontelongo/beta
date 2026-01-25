<?php

namespace App\Livewire\Agents\Properties\Upload;

use App\Enums\PropertySourceType;
use App\Jobs\GeocodePropertyJob;
use App\Models\Property;
use App\Models\PropertyImage;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.agent')]
#[Title('Compartir propiedad')]
class Sharing extends Component
{
    public string $sharingOption = 'private';

    #[Validate('nullable|numeric|min:1|max:100')]
    public ?float $commissionSplit = null;

    public string $customCommission = '';

    /** @var array<float> */
    public array $commissionPresets = [30.0, 40.0, 50.0];

    /**
     * Computed property for backward compatibility with blade templates.
     */
    #[Computed]
    public function isCollaborative(): bool
    {
        return $this->sharingOption === 'collaborative';
    }

    public function mount(): void
    {
        // Check if we have extracted data (required step)
        if (! session('property_upload.extracted_data')) {
            $this->redirectRoute('agents.properties.upload.describe', navigate: true);
        }
    }

    public function updatedSharingOption(): void
    {
        if ($this->sharingOption === 'collaborative' && ! $this->commissionSplit) {
            $this->commissionSplit = 50.0; // Default commission
        }
    }

    public function setCommission(float $value): void
    {
        $this->commissionSplit = $value;
        $this->customCommission = '';
    }

    public function updatedCustomCommission(): void
    {
        $value = (float) $this->customCommission;
        if ($value > 0 && $value <= 100) {
            $this->commissionSplit = $value;
        }
    }

    public function back(): void
    {
        $this->redirectRoute('agents.properties.upload.photos', navigate: true);
    }

    public function publish(): void
    {
        $isCollaborative = $this->isCollaborative;

        // Validate commission split if collaborative
        if ($isCollaborative) {
            $this->validate([
                'commissionSplit' => ['required', 'numeric', 'min:1', 'max:100'],
            ]);
        }

        $extractedData = session('property_upload.extracted_data');
        $photoPaths = session('property_upload.photos', []);
        $coverIndex = session('property_upload.cover_index', 0);

        if (! $extractedData) {
            $this->redirectRoute('agents.properties.upload.describe', navigate: true);

            return;
        }

        // Extract nested data from the AI extraction format
        $property = $extractedData['property'] ?? [];
        $pricing = $extractedData['pricing'] ?? [];

        // Flatten amenities from all categories
        $amenities = array_merge(
            $extractedData['amenities']['unit'] ?? [],
            $extractedData['amenities']['building'] ?? [],
            $extractedData['amenities']['services'] ?? [],
        );

        // Track temp files to clean up after successful transaction
        $tempFilesToClean = [];

        $commissionSplit = $this->commissionSplit;

        // Wrap property creation and photo saving in a transaction for atomicity
        $newProperty = DB::transaction(function () use ($property, $pricing, $amenities, $extractedData, $photoPaths, $coverIndex, &$tempFilesToClean, $isCollaborative, $commissionSplit) {
            // Create the property
            $newProperty = Property::create([
                'user_id' => Auth::id(),
                'source_type' => PropertySourceType::Native,
                'operation_type' => $property['operation_type'],
                'price' => $pricing['price'],
                'price_currency' => $pricing['price_currency'] ?? 'MXN',
                'is_collaborative' => $isCollaborative,
                'commission_split' => $isCollaborative ? $commissionSplit : null,
                'property_type' => $property['property_type'],
                'colonia' => $property['colonia'],
                'city' => $property['city'] ?? 'Guadalajara',
                'state' => $property['state'] ?? 'Jalisco',
                'address' => $property['address'] ?? '',
                'bedrooms' => $property['bedrooms'],
                'bathrooms' => $property['bathrooms'],
                'half_bathrooms' => $property['half_bathrooms'],
                'built_size_m2' => $property['built_size_m2'],
                'lot_size_m2' => $property['lot_size_m2'],
                'parking_spots' => $property['parking_spots'],
                'age_years' => $property['age_years'],
                'amenities' => $amenities,
                'description' => $extractedData['description'] ?? '',
                'original_description' => session('property_upload.description'),
                // Store the full AI extracted data (terms, pricing details, etc.)
                'ai_extracted_data' => $extractedData,
            ]);

            // Move uploaded photos from temp to permanent storage
            foreach ($photoPaths as $index => $tempPath) {
                if (Storage::disk('local')->exists($tempPath)) {
                    $permanentPath = 'property-images/'.$newProperty->id.'/'.basename($tempPath);
                    Storage::disk('public')->put(
                        $permanentPath,
                        Storage::disk('local')->get($tempPath)
                    );

                    PropertyImage::create([
                        'property_id' => $newProperty->id,
                        'path' => $permanentPath,
                        'original_filename' => basename($tempPath),
                        'size_bytes' => Storage::disk('public')->size($permanentPath),
                        'position' => $index,
                        'is_cover' => $index === $coverIndex,
                    ]);

                    // Track temp file for cleanup after transaction commits
                    $tempFilesToClean[] = $tempPath;
                }
            }

            return $newProperty;
        });

        // Clean up temp files only after successful transaction
        foreach ($tempFilesToClean as $tempPath) {
            Storage::disk('local')->delete($tempPath);
        }

        // Clear cached property count
        Cache::forget('user.'.Auth::id().'.my_properties_count');

        // Dispatch geocoding job (outside transaction - async job)
        GeocodePropertyJob::dispatch($newProperty->id);

        // Clear session data
        session()->forget([
            'property_upload.description',
            'property_upload.extracted_data',
            'property_upload.quality_score',
            'property_upload.extraction_id',
            'property_upload.extraction_started_at',
            'property_upload.photos',
            'property_upload.cover_index',
        ]);

        // Store property ID for complete page
        session(['property_upload.completed_id' => $newProperty->id]);

        Flux::toast(
            heading: 'Propiedad publicada',
            text: 'Tu propiedad ya esta disponible.',
            variant: 'success',
        );

        $this->redirectRoute('agents.properties.upload.complete', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.agents.properties.upload.sharing');
    }
}
