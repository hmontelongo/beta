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

    it('reorders photos and updates cover index', function () {
        // Set up session with saved photos
        session([
            'property_upload.photos' => ['path/photo1.jpg', 'path/photo2.jpg', 'path/photo3.jpg'],
            'property_upload.cover_index' => 0, // First photo is cover
        ]);

        $component = Livewire::test(Photos::class);

        // Verify initial state
        $component->assertSet('savedPhotoPaths', ['path/photo1.jpg', 'path/photo2.jpg', 'path/photo3.jpg'])
            ->assertSet('coverIndex', 0);

        // Reorder: move photo at index 0 to index 2
        $component->call('reorderPhoto', 0, 2);

        // Photo order should now be: photo2, photo3, photo1
        $component->assertSet('savedPhotoPaths', ['path/photo2.jpg', 'path/photo3.jpg', 'path/photo1.jpg'])
            ->assertSet('coverIndex', 2); // Cover index should follow the photo

        // Verify session was updated
        expect(session('property_upload.photos'))->toBe(['path/photo2.jpg', 'path/photo3.jpg', 'path/photo1.jpg']);
        expect(session('property_upload.cover_index'))->toBe(2);
    });

    it('reorders photos without changing cover when cover is not moved', function () {
        session([
            'property_upload.photos' => ['path/photo1.jpg', 'path/photo2.jpg', 'path/photo3.jpg'],
            'property_upload.cover_index' => 2, // Third photo is cover
        ]);

        $component = Livewire::test(Photos::class);

        // Move photo 0 to position 1 (cover at position 2 should not be affected)
        $component->call('reorderPhoto', 0, 1);

        // Photo order should now be: photo2, photo1, photo3
        $component->assertSet('savedPhotoPaths', ['path/photo2.jpg', 'path/photo1.jpg', 'path/photo3.jpg'])
            ->assertSet('coverIndex', 2); // Cover index unchanged
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
            ->assertSet('sharingOption', 'private');
    });

    it('sets default commission when enabling collaboration', function () {
        Livewire::test(Sharing::class)
            ->set('sharingOption', 'collaborative')
            ->assertSet('commissionSplit', 50.0);
    });

    it('allows setting commission preset', function () {
        Livewire::test(Sharing::class)
            ->set('sharingOption', 'collaborative')
            ->call('setCommission', 30.0)
            ->assertSet('commissionSplit', 30.0);
    });

    it('creates property on publish', function () {
        Livewire::test(Sharing::class)
            ->set('sharingOption', 'private')
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
            ->set('sharingOption', 'collaborative')
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

describe('Review Screen - Extraction Recovery', function () {
    beforeEach(function () {
        session(['property_upload.description' => 'Casa en Providencia, 4 recamaras, 3 banos, 350m2 en venta']);
    });

    it('recovers existing extraction on page reload', function () {
        $extractionId = \Illuminate\Support\Str::uuid()->toString();
        $cacheKey = \App\Jobs\ExtractPropertyDescriptionJob::getCacheKey($extractionId);

        // Simulate an in-progress extraction
        session([
            'property_upload.extraction_id' => $extractionId,
            'property_upload.extraction_started_at' => now()->timestamp,
        ]);
        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'status' => 'processing',
            'stage' => 'Analizando descripcion con IA...',
            'progress' => 30,
            'data' => null,
            'error' => null,
        ], now()->addHours(2));

        // Mount the component (simulating a page reload)
        Livewire::test(Review::class)
            ->assertSet('extractionId', $extractionId)
            ->assertSet('extractionStatus', 'processing')
            ->assertSet('extractionProgress', 30);

        // Verify no new extraction was started (extraction ID should be the same)
        expect(session('property_upload.extraction_id'))->toBe($extractionId);
    });

    it('starts fresh extraction when cache expires', function () {
        $oldExtractionId = \Illuminate\Support\Str::uuid()->toString();

        // Simulate expired extraction (in session but not in cache)
        session([
            'property_upload.extraction_id' => $oldExtractionId,
            'property_upload.extraction_started_at' => now()->subHours(3)->timestamp,
        ]);
        // Do NOT put anything in cache - simulating expiry

        // Mount the component
        $component = Livewire::test(Review::class);

        // Should start a new extraction
        expect($component->get('extractionId'))->not->toBe($oldExtractionId);
        expect($component->get('extractionStatus'))->toBe('queued');
    });

    it('recovers completed extraction on page reload', function () {
        $extractionId = \Illuminate\Support\Str::uuid()->toString();
        $cacheKey = \App\Jobs\ExtractPropertyDescriptionJob::getCacheKey($extractionId);

        $completedData = [
            'property' => [
                'property_type' => PropertyType::House->value,
                'operation_type' => OperationType::Sale->value,
                'colonia' => 'Providencia',
                'city' => 'Guadalajara',
                'state' => 'Jalisco',
            ],
            'pricing' => ['price' => 5000000, 'price_currency' => 'MXN'],
            'amenities' => ['unit' => [], 'building' => [], 'services' => []],
            'quality_score' => 75,
        ];

        // Simulate a completed extraction
        session([
            'property_upload.extraction_id' => $extractionId,
            'property_upload.extraction_started_at' => now()->timestamp,
        ]);
        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'status' => 'completed',
            'stage' => 'Datos extraidos',
            'progress' => 100,
            'data' => $completedData,
            'error' => null,
        ], now()->addHours(2));

        // Mount the component
        Livewire::test(Review::class)
            ->assertSet('extractionStatus', 'completed')
            ->assertSet('extractedData.property.colonia', 'Providencia')
            ->assertSet('qualityScore', 75);

        // Cache should be cleaned up
        expect(\Illuminate\Support\Facades\Cache::get($cacheKey))->toBeNull();
    });

    it('times out after max duration', function () {
        $extractionId = \Illuminate\Support\Str::uuid()->toString();
        $cacheKey = \App\Jobs\ExtractPropertyDescriptionJob::getCacheKey($extractionId);

        // Simulate an extraction that started 6 minutes ago (beyond 5 min timeout)
        session([
            'property_upload.extraction_id' => $extractionId,
            'property_upload.extraction_started_at' => now()->subSeconds(360)->timestamp,
        ]);
        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'status' => 'processing',
            'stage' => 'Analizando...',
            'progress' => 50,
            'data' => null,
            'error' => null,
        ], now()->addHours(2));

        // Mount the component
        $component = Livewire::test(Review::class)
            ->assertSet('extractionId', $extractionId)
            ->assertSet('extractionStatus', 'processing');

        // Trigger a status check which should detect the timeout
        $component->call('checkExtractionStatus')
            ->assertSet('extractionStatus', 'failed')
            ->assertSet('extractionError', 'El proceso tomo demasiado tiempo. Por favor intenta de nuevo.');
    });
});
