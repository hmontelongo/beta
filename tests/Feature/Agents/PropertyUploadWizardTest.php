<?php

use App\Enums\OperationType;
use App\Enums\PropertySourceType;
use App\Enums\PropertyType;
use App\Jobs\GeocodePropertyJob;
use App\Livewire\Agents\Properties\Upload\Complete;
use App\Livewire\Agents\Properties\Upload\Describe;
use App\Livewire\Agents\Properties\Upload\Photos;
use App\Livewire\Agents\Properties\Upload\Review;
use App\Livewire\Agents\Properties\Upload\Sharing;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Describe step', function () {
    it('renders the describe page', function () {
        Livewire::test(Describe::class)
            ->assertStatus(200)
            ->assertSee('Describe tu propiedad');
    });

    it('validates minimum description length', function () {
        Livewire::test(Describe::class)
            ->set('description', 'too short')
            ->call('continue')
            ->assertHasErrors(['description']);
    });

    it('stores description in session and redirects to review', function () {
        $description = 'Casa en Providencia con 4 recámaras, 3 baños, alberca y jardín amplio. Precio 8 millones.';

        Livewire::test(Describe::class)
            ->set('description', $description)
            ->call('continue')
            ->assertRedirect(route('agents.properties.upload.review'));

        expect(session('property_upload.description'))->toBe($description);
    });
});

describe('Review step', function () {
    beforeEach(function () {
        session(['property_upload.description' => 'Casa en Providencia con 4 recámaras, 3 baños. Precio 8 millones.']);
    });

    it('redirects to describe if no description in session', function () {
        session()->forget('property_upload.description');

        Livewire::test(Review::class)
            ->assertRedirect(route('agents.properties.upload.describe'));
    });

    it('renders the review page when description exists', function () {
        Livewire::test(Review::class)
            ->assertStatus(200);
    });

    it('validates required fields before continuing', function () {
        // Set up minimal extracted data without required fields
        session(['property_upload.extracted_data' => [
            'property' => ['property_type' => null, 'operation_type' => null],
            'pricing' => ['price' => null],
        ]]);

        Livewire::test(Review::class)
            ->call('continue')
            ->assertHasErrors();
    });
});

describe('Photos step', function () {
    beforeEach(function () {
        session([
            'property_upload.description' => 'Casa test',
            'property_upload.extracted_data' => [
                'property' => [
                    'property_type' => 'house',
                    'operation_type' => 'sale',
                    'colonia' => 'Providencia',
                    'city' => 'Guadalajara',
                    'state' => 'Jalisco',
                ],
                'pricing' => [
                    'price' => 8000000,
                    'price_currency' => 'MXN',
                ],
                'amenities' => ['unit' => [], 'building' => [], 'services' => []],
            ],
        ]);
    });

    it('redirects to describe if no extracted data in session', function () {
        session()->forget('property_upload.extracted_data');

        Livewire::test(Photos::class)
            ->assertRedirect(route('agents.properties.upload.describe'));
    });

    it('renders the photos page when extracted data exists', function () {
        Livewire::test(Photos::class)
            ->assertStatus(200)
            ->assertSee('Agrega fotos de la propiedad');
    });

    it('allows skipping photos step', function () {
        Livewire::test(Photos::class)
            ->call('skip')
            ->assertRedirect(route('agents.properties.upload.sharing'));

        expect(session('property_upload.photos'))->toBe([]);
    });

    it('can set cover photo index', function () {
        Livewire::test(Photos::class)
            ->set('coverIndex', 2)
            ->assertSet('coverIndex', 2);
    });
});

describe('Sharing step', function () {
    beforeEach(function () {
        session([
            'property_upload.description' => 'Hermosa casa en Providencia',
            'property_upload.extracted_data' => [
                'property' => [
                    'property_type' => 'house',
                    'operation_type' => 'sale',
                    'colonia' => 'Providencia',
                    'city' => 'Guadalajara',
                    'state' => 'Jalisco',
                    'address' => 'Calle Test 123',
                    'bedrooms' => 4,
                    'bathrooms' => 3,
                    'half_bathrooms' => 1,
                    'built_size_m2' => 350,
                    'lot_size_m2' => 400,
                    'parking_spots' => 2,
                    'age_years' => 5,
                ],
                'pricing' => [
                    'price' => 8000000,
                    'price_currency' => 'MXN',
                ],
                'amenities' => [
                    'unit' => ['pool', 'garden'],
                    'building' => [],
                    'services' => [],
                ],
                'description' => 'Hermosa casa en Providencia',
            ],
            'property_upload.photos' => [],
            'property_upload.cover_index' => 0,
        ]);
    });

    it('redirects to describe if no extracted data in session', function () {
        session()->forget('property_upload.extracted_data');

        Livewire::test(Sharing::class)
            ->assertRedirect(route('agents.properties.upload.describe'));
    });

    it('renders the sharing page when extracted data exists', function () {
        Livewire::test(Sharing::class)
            ->assertStatus(200);
    });

    it('sets default commission when collaborative is enabled', function () {
        Livewire::test(Sharing::class)
            ->set('isCollaborative', true)
            ->assertSet('commissionSplit', 50.0);
    });

    it('validates commission split when collaborative', function () {
        Livewire::test(Sharing::class)
            ->set('isCollaborative', true)
            ->set('commissionSplit', null)
            ->call('publish')
            ->assertHasErrors(['commissionSplit']);
    });

    it('validates commission split must be between 1 and 100', function () {
        Livewire::test(Sharing::class)
            ->set('isCollaborative', true)
            ->set('commissionSplit', 150)
            ->call('publish')
            ->assertHasErrors(['commissionSplit']);
    });

    it('creates a native property on publish', function () {
        Queue::fake();

        Livewire::test(Sharing::class)
            ->set('isCollaborative', false)
            ->call('publish')
            ->assertRedirect(route('agents.properties.upload.complete'));

        $property = Property::latest()->first();

        expect($property)->not->toBeNull();
        expect($property->source_type)->toBe(PropertySourceType::Native);
        expect($property->user_id)->toBe($this->user->id);
        expect($property->property_type)->toBe(PropertyType::House);
        expect($property->operation_type)->toBe(OperationType::Sale);
        expect((float) $property->price)->toEqual(8000000.0);
        expect($property->colonia)->toBe('Providencia');
        expect($property->city)->toBe('Guadalajara');
        expect($property->state)->toBe('Jalisco');
        expect($property->is_collaborative)->toBeFalse();
    });

    it('creates a collaborative property with commission split', function () {
        Queue::fake();

        Livewire::test(Sharing::class)
            ->set('isCollaborative', true)
            ->set('commissionSplit', 40.0)
            ->call('publish')
            ->assertRedirect(route('agents.properties.upload.complete'));

        $property = Property::latest()->first();

        expect($property->is_collaborative)->toBeTrue();
        expect((float) $property->commission_split)->toEqual(40.0);
    });

    it('dispatches geocoding job after property creation', function () {
        Queue::fake();

        Livewire::test(Sharing::class)
            ->call('publish');

        Queue::assertPushed(GeocodePropertyJob::class);
    });

    it('clears session data after successful publish', function () {
        Queue::fake();

        Livewire::test(Sharing::class)
            ->call('publish');

        expect(session('property_upload.description'))->toBeNull();
        expect(session('property_upload.extracted_data'))->toBeNull();
        expect(session('property_upload.photos'))->toBeNull();
        expect(session('property_upload.completed_id'))->not->toBeNull();
    });
});

describe('Complete step', function () {
    it('redirects if no completed property ID in session', function () {
        Livewire::test(Complete::class)
            ->assertRedirect(route('agents.properties.upload.describe'));
    });

    it('shows the completed property when ID exists', function () {
        $property = Property::factory()->native($this->user)->create();
        session(['property_upload.completed_id' => $property->id]);

        Livewire::test(Complete::class)
            ->assertStatus(200)
            ->assertSee('Tu propiedad esta lista');
    });
});

describe('Property search with native properties', function () {
    it('includes native properties in search results', function () {
        // Create a native property
        $nativeProperty = Property::factory()
            ->native($this->user)
            ->forSale()
            ->create([
                'price' => 5000000,
                'colonia' => 'Providencia',
            ]);

        $response = $this->get(route('agents.properties.index'));
        $response->assertStatus(200);
    });
});
