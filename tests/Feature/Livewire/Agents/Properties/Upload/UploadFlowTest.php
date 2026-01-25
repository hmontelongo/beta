<?php

use App\Enums\OperationType;
use App\Enums\PropertySourceType;
use App\Enums\PropertyType;
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
    $this->agent = User::factory()->agent()->create();
    $this->actingAs($this->agent);

    // Use sync queue driver for tests so jobs run immediately
    Queue::fake()->except([
        \App\Jobs\ExtractPropertyDescriptionJob::class,
    ]);
    config(['queue.default' => 'sync']);
});

describe('Describe Screen', function () {
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

    it('saves description to session and redirects', function () {
        $description = 'Tengo una casa en Providencia con 4 recamaras y 3 banos';

        Livewire::test(Describe::class)
            ->set('description', $description)
            ->call('continue')
            ->assertRedirect(route('agents.properties.upload.review'));

        expect(session('property_upload.description'))->toBe($description);
    });
});

describe('Review Screen', function () {
    beforeEach(function () {
        session(['property_upload.description' => 'Casa en Providencia, 4 recamaras, 3 banos, 350m2 en venta']);

        // Mock the AI extraction service to avoid actual API calls
        $this->mock(\App\Services\AI\PropertyDescriptionExtractionService::class)
            ->shouldReceive('extract')
            ->andReturn([
                'property' => [
                    'property_type' => PropertyType::House->value,
                    'operation_type' => OperationType::Sale->value,
                    'colonia' => 'Providencia',
                    'city' => 'Guadalajara',
                    'state' => 'Jalisco',
                    'address' => null,
                    'bedrooms' => 4,
                    'bathrooms' => 3,
                    'half_bathrooms' => null,
                    'built_size_m2' => 350,
                    'lot_size_m2' => null,
                    'parking_spots' => null,
                    'age_years' => null,
                ],
                'pricing' => [
                    'price' => 5000000,
                    'price_currency' => 'MXN',
                    'price_per_m2' => null,
                    'maintenance_fee' => null,
                    'extra_costs' => [],
                    'included_services' => [],
                ],
                'terms' => [
                    'deposit_months' => null,
                    'advance_months' => null,
                    'guarantor_required' => null,
                    'income_proof_months' => null,
                    'pets_allowed' => null,
                    'max_occupants' => null,
                    'restrictions' => [],
                ],
                'amenities' => [
                    'unit' => [],
                    'building' => [],
                    'services' => [],
                ],
                'location' => [
                    'building_name' => null,
                    'nearby' => [],
                ],
                'description' => 'Casa en Providencia con 4 recamaras y 3 banos.',
                'quality_score' => 75,
            ]);
    });

    it('renders the review page', function () {
        Livewire::test(Review::class)
            ->call('checkExtractionStatus') // Simulate polling
            ->assertStatus(200)
            ->assertSee('Datos extraidos');
    });

    it('extracts basic data from description via AI', function () {
        Livewire::test(Review::class)
            ->call('checkExtractionStatus') // Simulate polling to get data
            ->assertSet('extractedData.property.property_type', PropertyType::House->value)
            ->assertSet('extractedData.property.operation_type', OperationType::Sale->value)
            ->assertSet('extractedData.property.colonia', 'Providencia');
    });

    it('allows adding amenities to array', function () {
        Livewire::test(Review::class)
            ->call('checkExtractionStatus') // Get data first
            ->call('addToArray', 'amenities.unit', 'Alberca')
            ->assertSet('extractedData.amenities.unit', ['Alberca']);
    });

    it('allows removing amenities from array', function () {
        Livewire::test(Review::class)
            ->call('checkExtractionStatus') // Get data first
            ->set('extractedData.amenities.unit', ['Alberca', 'Gym'])
            ->call('removeFromArray', 'amenities.unit', 0)
            ->assertSet('extractedData.amenities.unit', ['Gym']);
    });

    it('saves data to session on continue', function () {
        Livewire::test(Review::class)
            ->call('checkExtractionStatus') // Get data first
            ->call('continue')
            ->assertRedirect(route('agents.properties.upload.photos'));

        expect(session('property_upload.extracted_data'))->toBeArray()
            ->and(session('property_upload.extracted_data.property.colonia'))->toBe('Providencia');
    });
});

describe('Photos Screen', function () {
    beforeEach(function () {
        session([
            'property_upload.description' => 'Test description',
            'property_upload.extracted_data' => [
                'property' => [
                    'property_type' => PropertyType::House->value,
                    'operation_type' => OperationType::Sale->value,
                    'colonia' => 'Providencia',
                    'city' => 'Guadalajara',
                    'state' => 'Jalisco',
                ],
                'pricing' => [
                    'price' => 5000000,
                    'price_currency' => 'MXN',
                ],
                'amenities' => ['unit' => [], 'building' => [], 'services' => []],
            ],
        ]);
    });

    it('renders the photos page', function () {
        Livewire::test(Photos::class)
            ->assertStatus(200)
            ->assertSee('Agrega fotos de la propiedad');
    });

    it('redirects to describe if no extracted data', function () {
        session()->forget('property_upload.extracted_data');

        Livewire::test(Photos::class)
            ->assertRedirect(route('agents.properties.upload.describe'));
    });

    it('allows skipping photos', function () {
        Livewire::test(Photos::class)
            ->call('skip')
            ->assertRedirect(route('agents.properties.upload.sharing'));
    });
});

describe('Sharing Screen', function () {
    beforeEach(function () {
        session([
            'property_upload.description' => 'Test description',
            'property_upload.extracted_data' => [
                'property' => [
                    'property_type' => PropertyType::House->value,
                    'operation_type' => OperationType::Sale->value,
                    'colonia' => 'Providencia',
                    'city' => 'Guadalajara',
                    'state' => 'Jalisco',
                    'address' => null,
                    'bedrooms' => 4,
                    'bathrooms' => 3,
                    'half_bathrooms' => null,
                    'built_size_m2' => 350,
                    'lot_size_m2' => null,
                    'parking_spots' => 2,
                    'age_years' => null,
                ],
                'pricing' => [
                    'price' => 5000000,
                    'price_currency' => 'MXN',
                ],
                'amenities' => [
                    'unit' => ['Alberca'],
                    'building' => [],
                    'services' => [],
                ],
                'description' => 'Test description',
            ],
            'property_upload.photos' => [],
            'property_upload.cover_index' => 0,
        ]);
    });

    it('renders the sharing page', function () {
        Livewire::test(Sharing::class)
            ->assertStatus(200)
            ->assertSee('Configuracion de colaboracion');
    });

    it('defaults to non-collaborative', function () {
        Livewire::test(Sharing::class)
            ->assertSet('isCollaborative', false);
    });

    it('sets default commission when enabling collaboration', function () {
        Livewire::test(Sharing::class)
            ->set('isCollaborative', true)
            ->assertSet('commissionSplit', 50.0);
    });

    it('allows setting commission preset', function () {
        Livewire::test(Sharing::class)
            ->set('isCollaborative', true)
            ->call('setCommission', 30.0)
            ->assertSet('commissionSplit', 30.0);
    });

    it('creates property on publish', function () {
        Livewire::test(Sharing::class)
            ->set('isCollaborative', false)
            ->call('publish')
            ->assertRedirect(route('agents.properties.upload.complete'));

        expect(Property::where('user_id', $this->agent->id)->first())
            ->not->toBeNull()
            ->source_type->toBe(PropertySourceType::Native)
            ->colonia->toBe('Providencia')
            ->price->toBe('5000000.00')
            ->is_collaborative->toBeFalse();
    });

    it('creates collaborative property with commission', function () {
        Livewire::test(Sharing::class)
            ->set('isCollaborative', true)
            ->call('setCommission', 40.0)
            ->call('publish');

        expect(Property::where('user_id', $this->agent->id)->first())
            ->is_collaborative->toBeTrue()
            ->commission_split->toBe('40.00');
    });
});

describe('Complete Screen', function () {
    it('renders the complete page with property', function () {
        $property = Property::factory()->native($this->agent)->create();
        session(['property_upload.completed_id' => $property->id]);

        Livewire::test(Complete::class)
            ->assertStatus(200)
            ->assertSee('Tu propiedad esta lista');
    });

    it('redirects to describe if no completed property', function () {
        Livewire::test(Complete::class)
            ->assertRedirect(route('agents.properties.upload.describe'));
    });
});
