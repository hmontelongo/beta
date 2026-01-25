<?php

namespace App\Livewire\Agents\Properties;

use App\Enums\OperationType;
use App\Enums\PropertyType;
use App\Livewire\Concerns\HasNestedData;
use App\Models\Property;
use App\Models\PropertyImage;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.agent')]
class Edit extends Component
{
    use AuthorizesRequests;
    use HasNestedData;
    use WithFileUploads;

    public Property $property;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    #[Validate(['photos.*' => 'image|max:10240'])]
    public array $photos = [];

    public function mount(Property $property): void
    {
        $this->authorize('update', $property);

        $this->property = $property->load('propertyImages');

        // Load property data into structured format
        $this->data = [
            'property' => [
                'property_type' => $property->property_type?->value,
                'operation_type' => $property->operation_type?->value,
                'colonia' => $property->colonia,
                'city' => $property->city ?? 'Guadalajara',
                'state' => $property->state ?? 'Jalisco',
                'address' => $property->address,
                'interior_number' => $property->interior_number,
                'postal_code' => $property->postal_code,
                'bedrooms' => $property->bedrooms,
                'bathrooms' => $property->bathrooms,
                'half_bathrooms' => $property->half_bathrooms,
                'built_size_m2' => $property->built_size_m2,
                'lot_size_m2' => $property->lot_size_m2,
                'parking_spots' => $property->parking_spots,
                'age_years' => $property->age_years,
            ],
            'pricing' => [
                'price' => $property->price,
                'price_currency' => $property->price_currency ?? 'MXN',
                // Load maintenance_fee from ai_extracted_data (where it's stored for native properties)
                'maintenance_fee' => $property->ai_extracted_data['pricing']['maintenance_fee'] ?? null,
                'included_services' => $property->ai_extracted_data['pricing']['included_services'] ?? [],
            ],
            'terms' => $property->ai_extracted_data['terms'] ?? [
                'deposit_months' => null,
                'advance_months' => null,
                'guarantor_required' => null,
                'pets_allowed' => null,
                'max_occupants' => null,
                'restrictions' => [],
            ],
            'amenities' => $property->ai_extracted_data['amenities'] ?? [
                'unit' => [],
                'building' => [],
                'services' => [],
            ],
            'location' => [
                'building_name' => $property->ai_extracted_data['location']['building_name'] ?? null,
            ],
            'collaboration' => [
                'is_collaborative' => $property->is_collaborative ?? false,
                'commission_split' => $property->commission_split,
            ],
            'description' => $property->description,
        ];
    }

    /**
     * Get count of collections using this property (excluding own).
     */
    #[Computed]
    public function collectionsUsingCount(): int
    {
        return $this->property->collections()
            ->where('user_id', '!=', auth()->id())
            ->count();
    }

    /**
     * Get existing property images.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PropertyImage>
     */
    #[Computed]
    public function existingImages(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->property->propertyImages;
    }

    /**
     * Process uploaded photos.
     */
    public function updatedPhotos(): void
    {
        $this->validateOnly('photos.*');

        $maxPosition = $this->property->propertyImages()->max('position') ?? 0;

        foreach ($this->photos as $photo) {
            $path = $photo->store('properties/'.$this->property->id, 'public');

            $this->property->propertyImages()->create([
                'path' => $path,
                'original_filename' => $photo->getClientOriginalName(),
                'size_bytes' => $photo->getSize(),
                'position' => ++$maxPosition,
                'is_cover' => $this->property->propertyImages()->count() === 0,
            ]);
        }

        $this->photos = [];
        $this->property->load('propertyImages');
        unset($this->existingImages);

        Flux::toast(
            text: 'Fotos agregadas',
            variant: 'success',
        );
    }

    /**
     * Remove a photo from the property.
     */
    public function removePhoto(int $imageId): void
    {
        $image = $this->property->propertyImages()->findOrFail($imageId);
        $wasCover = $image->is_cover;

        Storage::disk('public')->delete($image->path);
        $image->delete();

        if ($wasCover) {
            $this->property->propertyImages()->orderBy('position')->first()?->update(['is_cover' => true]);
        }

        $this->property->load('propertyImages');
        unset($this->existingImages);

        Flux::toast(
            text: 'Foto eliminada',
            variant: 'success',
        );
    }

    /**
     * Set an image as the cover photo.
     */
    public function setCover(int $imageId): void
    {
        $this->property->propertyImages()->update(['is_cover' => false]);
        $this->property->propertyImages()->where('id', $imageId)->update(['is_cover' => true]);

        $this->property->load('propertyImages');
        unset($this->existingImages);

        Flux::toast(
            text: 'Portada actualizada',
            variant: 'success',
        );
    }

    /**
     * Reorder photos via drag and drop.
     */
    public function reorderPhoto(int|string $item, int $position): void
    {
        $this->property->propertyImages()
            ->where('id', (int) $item)
            ->update(['position' => $position]);

        $this->property->load('propertyImages');
        unset($this->existingImages);
    }

    public function save(): void
    {
        $property = $this->data['property'] ?? [];
        $pricing = $this->data['pricing'] ?? [];
        $collaboration = $this->data['collaboration'] ?? [];

        // Validate required fields
        if (empty($property['property_type'])) {
            $this->addError('property.property_type', 'Selecciona el tipo de propiedad.');

            return;
        }

        if (empty($property['operation_type'])) {
            $this->addError('property.operation_type', 'Selecciona el tipo de operación.');

            return;
        }

        if (empty($pricing['price']) || $pricing['price'] < 1000) {
            $this->addError('pricing.price', 'Ingresa un precio válido (mínimo $1,000).');

            return;
        }

        if (empty($property['colonia'])) {
            $this->addError('property.colonia', 'Ingresa la colonia.');

            return;
        }

        // Build ai_extracted_data from structured fields
        $aiExtractedData = $this->property->ai_extracted_data ?? [];
        $aiExtractedData['pricing'] = array_merge($aiExtractedData['pricing'] ?? [], [
            'maintenance_fee' => $this->data['pricing']['maintenance_fee'] ?? null,
            'included_services' => $this->data['pricing']['included_services'] ?? [],
        ]);
        $aiExtractedData['terms'] = $this->data['terms'] ?? [];
        $aiExtractedData['amenities'] = $this->data['amenities'] ?? [];
        $aiExtractedData['location'] = array_merge($aiExtractedData['location'] ?? [], [
            'building_name' => $this->data['location']['building_name'] ?? null,
        ]);

        // Merge amenities into flat array for the amenities column
        $flatAmenities = array_merge(
            $this->data['amenities']['unit'] ?? [],
            $this->data['amenities']['building'] ?? [],
            $this->data['amenities']['services'] ?? []
        );

        $isCollaborative = $collaboration['is_collaborative'] ?? false;

        $this->property->update([
            'property_type' => PropertyType::from($property['property_type']),
            'operation_type' => OperationType::from($property['operation_type']),
            'bedrooms' => $property['bedrooms'],
            'bathrooms' => $property['bathrooms'],
            'half_bathrooms' => $property['half_bathrooms'],
            'built_size_m2' => $property['built_size_m2'],
            'lot_size_m2' => $property['lot_size_m2'],
            'parking_spots' => $property['parking_spots'],
            'age_years' => $property['age_years'],
            'price' => $pricing['price'],
            'price_currency' => $pricing['price_currency'] ?? 'MXN',
            // maintenance_fee is stored in ai_extracted_data['pricing']['maintenance_fee'], not as a column
            'address' => $property['address'],
            'interior_number' => $property['interior_number'],
            'colonia' => $property['colonia'],
            'city' => $property['city'] ?? 'Guadalajara',
            'state' => $property['state'] ?? 'Jalisco',
            'postal_code' => $property['postal_code'],
            'description' => $this->data['description'],
            'is_collaborative' => $isCollaborative,
            'commission_split' => $isCollaborative ? $collaboration['commission_split'] : null,
            'amenities' => $flatAmenities ?: null,
            'ai_extracted_data' => $aiExtractedData,
        ]);

        Flux::toast(
            heading: 'Cambios guardados',
            text: 'La propiedad ha sido actualizada.',
            variant: 'success',
        );

        $this->redirectRoute('agents.properties.show', $this->property, navigate: true);
    }

    /**
     * Delete the property.
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->property);

        foreach ($this->property->propertyImages as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $this->property->delete();

        Cache::forget('user.'.auth()->id().'.my_properties_count');

        Flux::toast(
            text: 'Propiedad eliminada',
            variant: 'success',
        );

        $this->redirectRoute('agents.properties.index', navigate: true);
    }

    public function cancel(): void
    {
        $this->redirectRoute('agents.properties.show', $this->property, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.agents.properties.edit', [
            'propertyTypes' => PropertyType::cases(),
            'operationTypes' => OperationType::cases(),
        ])->title('Editar propiedad');
    }
}
