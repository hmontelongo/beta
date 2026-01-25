<?php

use App\Enums\OperationType;
use App\Enums\PropertyType;
use App\Livewire\Agents\Properties\Edit;
use App\Models\Collection;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

describe('authorization', function () {
    it('requires authentication', function () {
        $property = Property::factory()->native()->create();

        $this->get(route('agents.properties.edit', $property))
            ->assertRedirect(route('login'));
    });

    it('denies access to non-owners', function () {
        $owner = User::factory()->agent()->create();
        $otherAgent = User::factory()->agent()->create();
        $property = Property::factory()->native($owner)->create();

        $this->actingAs($otherAgent)
            ->get(route('agents.properties.edit', $property))
            ->assertForbidden();
    });

    it('denies access to scraped properties', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->create(); // scraped by default

        $this->actingAs($agent)
            ->get(route('agents.properties.edit', $property))
            ->assertForbidden();
    });

    it('allows owner to access edit page', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        $this->actingAs($agent)
            ->get(route('agents.properties.edit', $property))
            ->assertOk();
    });
});

describe('loading property data', function () {
    it('loads property data into structured data array', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create([
            'property_type' => PropertyType::Apartment,
            'operation_type' => OperationType::Rent,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price' => 25000,
            'colonia' => 'Providencia',
            'city' => 'Guadalajara',
            'is_collaborative' => true,
            'commission_split' => 40,
        ]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->assertSet('data.property.property_type', 'apartment')
            ->assertSet('data.property.operation_type', 'rent')
            ->assertSet('data.property.bedrooms', 3)
            ->assertSet('data.property.bathrooms', 2)
            ->assertSet('data.pricing.price', '25000.00')
            ->assertSet('data.property.colonia', 'Providencia')
            ->assertSet('data.property.city', 'Guadalajara')
            ->assertSet('data.collaboration.is_collaborative', true)
            ->assertSet('data.collaboration.commission_split', 40);
    });

    it('loads terms and amenities from ai_extracted_data', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create([
            'ai_extracted_data' => [
                'terms' => [
                    'deposit_months' => 2,
                    'pets_allowed' => true,
                ],
                'amenities' => [
                    'unit' => ['AC', 'Balcon'],
                    'building' => ['Gym'],
                ],
                'pricing' => [
                    'included_services' => ['Agua', 'Gas'],
                ],
            ],
        ]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->assertSet('data.terms.deposit_months', 2)
            ->assertSet('data.terms.pets_allowed', true)
            ->assertSet('data.amenities.unit', ['AC', 'Balcon'])
            ->assertSet('data.amenities.building', ['Gym'])
            ->assertSet('data.pricing.included_services', ['Agua', 'Gas']);
    });
});

describe('saving changes', function () {
    it('saves property updates via updateValue', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create([
            'bedrooms' => 2,
            'price' => 20000,
        ]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('updateValue', 'property.bedrooms', 4)
            ->call('updateValue', 'pricing.price', 30000)
            ->call('save')
            ->assertRedirect(route('agents.properties.show', $property));

        $property->refresh();
        expect($property->bedrooms)->toBe(4)
            ->and($property->price)->toBe('30000.00');
    });

    it('validates required fields', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('updateValue', 'property.property_type', '')
            ->call('updateValue', 'property.operation_type', '')
            ->call('updateValue', 'pricing.price', null)
            ->call('updateValue', 'property.colonia', '')
            ->call('save')
            ->assertHasErrors(['property.property_type']);
    });

    it('clears commission_split when collaboration is disabled', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->collaborative($agent)->create([
            'is_collaborative' => true,
            'commission_split' => 40,
        ]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('updateValue', 'collaboration.is_collaborative', false)
            ->call('save');

        $property->refresh();
        expect($property->is_collaborative)->toBeFalse()
            ->and($property->commission_split)->toBeNull();
    });

    it('saves amenities to ai_extracted_data', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('addToArray', 'amenities.unit', 'AC')
            ->call('addToArray', 'amenities.building', 'Gym')
            ->call('save');

        $property->refresh();
        expect($property->ai_extracted_data['amenities']['unit'])->toContain('AC')
            ->and($property->ai_extracted_data['amenities']['building'])->toContain('Gym');
    });

    it('saves rental terms to ai_extracted_data', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create([
            'operation_type' => OperationType::Rent,
        ]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('updateValue', 'terms.deposit_months', 2)
            ->call('updateValue', 'terms.pets_allowed', true)
            ->call('save');

        $property->refresh();
        expect($property->ai_extracted_data['terms']['deposit_months'])->toBe(2)
            ->and($property->ai_extracted_data['terms']['pets_allowed'])->toBeTrue();
    });

    it('saves maintenance_fee to ai_extracted_data', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create([
            'operation_type' => OperationType::Rent,
        ]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('updateValue', 'pricing.maintenance_fee', 2500)
            ->call('save');

        $property->refresh();
        expect($property->ai_extracted_data['pricing']['maintenance_fee'])->toBe(2500);
    });

    it('loads and displays maintenance_fee from ai_extracted_data', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create([
            'ai_extracted_data' => [
                'pricing' => [
                    'maintenance_fee' => 3500,
                ],
            ],
        ]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->assertSet('data.pricing.maintenance_fee', 3500);
    });

    it('includes maintenance_fee in primary_price for native properties', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create([
            'price' => 25000,
            'operation_type' => OperationType::Rent,
            'ai_extracted_data' => [
                'pricing' => [
                    'maintenance_fee' => 4000,
                ],
            ],
        ]);

        expect($property->primary_price)->toMatchArray([
            'type' => 'rent',
            'price' => 25000.0,
            'currency' => 'MXN',
            'maintenance_fee' => 4000,
        ]);
    });
});

describe('data manipulation methods', function () {
    it('updates nested values via updateValue', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        $component = Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('updateValue', 'property.bedrooms', 5)
            ->assertSet('data.property.bedrooms', 5);
    });

    it('converts empty string to null', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create([
            'bedrooms' => 3,
        ]);

        $component = Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('updateValue', 'property.bedrooms', '')
            ->assertSet('data.property.bedrooms', null);
    });

    it('adds items to array via addToArray', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('addToArray', 'amenities.unit', 'Balcon')
            ->assertSet('data.amenities.unit', ['Balcon']);
    });

    it('does not add duplicate items', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create([
            'ai_extracted_data' => [
                'amenities' => ['unit' => ['Balcon']],
            ],
        ]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('addToArray', 'amenities.unit', 'Balcon')
            ->assertSet('data.amenities.unit', ['Balcon']);
    });

    it('removes items from array via removeFromArray', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create([
            'ai_extracted_data' => [
                'amenities' => ['unit' => ['AC', 'Balcon', 'Closet']],
            ],
        ]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('removeFromArray', 'amenities.unit', 1)
            ->assertSet('data.amenities.unit', ['AC', 'Closet']);
    });
});

describe('photo management', function () {
    it('displays existing images', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();
        PropertyImage::factory()->for($property)->count(3)->create();

        $component = Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property]);

        expect($component->instance()->existingImages)->toHaveCount(3);
    });

    it('uploads new photos', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        $file = UploadedFile::fake()->image('photo.jpg');

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->set('photos', [$file]);

        $property->refresh();
        expect($property->propertyImages()->count())->toBe(1);
        Storage::disk('public')->assertExists($property->propertyImages->first()->path);
    });

    it('sets first uploaded photo as cover', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        $file = UploadedFile::fake()->image('photo.jpg');

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->set('photos', [$file]);

        expect($property->propertyImages()->first()->is_cover)->toBeTrue();
    });

    it('removes a photo', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();
        $image = PropertyImage::factory()->for($property)->create([
            'path' => 'test/image.jpg',
        ]);

        // Create the fake file
        Storage::disk('public')->put('test/image.jpg', 'fake content');

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('removePhoto', $image->id);

        expect($property->propertyImages()->count())->toBe(0);
        Storage::disk('public')->assertMissing('test/image.jpg');
    });

    it('sets new cover when current cover is removed', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        $cover = PropertyImage::factory()->for($property)->create([
            'is_cover' => true,
            'position' => 1,
            'path' => 'test/cover.jpg',
        ]);
        $other = PropertyImage::factory()->for($property)->create([
            'is_cover' => false,
            'position' => 2,
            'path' => 'test/other.jpg',
        ]);

        Storage::disk('public')->put('test/cover.jpg', 'fake');
        Storage::disk('public')->put('test/other.jpg', 'fake');

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('removePhoto', $cover->id);

        $other->refresh();
        expect($other->is_cover)->toBeTrue();
    });

    it('sets cover photo', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        $image1 = PropertyImage::factory()->for($property)->create(['is_cover' => true]);
        $image2 = PropertyImage::factory()->for($property)->create(['is_cover' => false]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('setCover', $image2->id);

        $image1->refresh();
        $image2->refresh();

        expect($image1->is_cover)->toBeFalse()
            ->and($image2->is_cover)->toBeTrue();
    });

    it('reorders photos', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        $image1 = PropertyImage::factory()->for($property)->create(['position' => 1]);
        $image2 = PropertyImage::factory()->for($property)->create(['position' => 2]);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('reorderPhoto', $image1->id, 3);

        $image1->refresh();
        expect($image1->position)->toBe(3);
    });
});

describe('delete functionality', function () {
    it('deletes property and redirects', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();
        $propertyId = $property->id;

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('delete')
            ->assertRedirect(route('agents.properties.index'));

        // Property should be soft deleted
        expect(Property::find($propertyId))->toBeNull()
            ->and(Property::withTrashed()->find($propertyId))->not->toBeNull();
    });

    it('deletes associated images from storage', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        Storage::disk('public')->put('properties/1/image1.jpg', 'fake');
        Storage::disk('public')->put('properties/1/image2.jpg', 'fake');

        PropertyImage::factory()->for($property)->create(['path' => 'properties/1/image1.jpg']);
        PropertyImage::factory()->for($property)->create(['path' => 'properties/1/image2.jpg']);

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('delete');

        Storage::disk('public')->assertMissing('properties/1/image1.jpg');
        Storage::disk('public')->assertMissing('properties/1/image2.jpg');
    });
});

describe('cancel action', function () {
    it('redirects to property show page', function () {
        $agent = User::factory()->agent()->create();
        $property = Property::factory()->native($agent)->create();

        Livewire::actingAs($agent)
            ->test(Edit::class, ['property' => $property])
            ->call('cancel')
            ->assertRedirect(route('agents.properties.show', $property));
    });
});

describe('collections using count', function () {
    it('counts collections from other agents', function () {
        $owner = User::factory()->agent()->create();
        $otherAgent = User::factory()->agent()->create();
        $property = Property::factory()->native($owner)->create();

        // Create collections from other agents that include this property
        $collection1 = Collection::factory()->for($otherAgent)->create();
        $collection1->properties()->attach($property);

        $collection2 = Collection::factory()->for($otherAgent)->create();
        $collection2->properties()->attach($property);

        // Owner's own collection should not be counted
        $ownCollection = Collection::factory()->for($owner)->create();
        $ownCollection->properties()->attach($property);

        $component = Livewire::actingAs($owner)
            ->test(Edit::class, ['property' => $property]);

        expect($component->instance()->collectionsUsingCount)->toBe(2);
    });

    it('returns zero when property is not in other collections', function () {
        $owner = User::factory()->agent()->create();
        $property = Property::factory()->native($owner)->create();

        // Only owner's collection
        $ownCollection = Collection::factory()->for($owner)->create();
        $ownCollection->properties()->attach($property);

        $component = Livewire::actingAs($owner)
            ->test(Edit::class, ['property' => $property]);

        expect($component->instance()->collectionsUsingCount)->toBe(0);
    });
});
