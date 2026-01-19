<?php

use App\Jobs\GeocodeListingJob;
use App\Models\Listing;
use App\Models\Platform;
use App\Services\Google\GeocodingService;

beforeEach(function () {
    $this->platform = Platform::factory()->create();
});

it('geocodes a listing with address and city', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'address' => 'Calzada Central 293',
            'city' => 'Zapopan',
            'state' => 'Jalisco',
        ],
        'geocode_status' => null,
    ]);

    $mockService = Mockery::mock(GeocodingService::class);
    $mockService->shouldReceive('geocode')
        ->once()
        ->with('Calzada Central 293', 'Zapopan', 'Jalisco')
        ->andReturn([
            'lat' => 20.65123456,
            'lng' => -103.35123456,
            'formatted_address' => 'Calzada Central 293, Zapopan, Jalisco, Mexico',
            'place_id' => 'test_place_id',
        ]);

    $job = new GeocodeListingJob($listing->id);
    $job->handle($mockService);

    $listing->refresh();

    expect($listing->latitude)->toBe('20.65123456')
        ->and($listing->longitude)->toBe('-103.35123456')
        ->and($listing->geocode_status)->toBe('success')
        ->and($listing->geocoded_at)->not->toBeNull();
});

it('skips listing without address or city', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'title' => 'Test listing',
        ],
        'geocode_status' => null,
    ]);

    $mockService = Mockery::mock(GeocodingService::class);
    $mockService->shouldNotReceive('geocode');

    $job = new GeocodeListingJob($listing->id);
    $job->handle($mockService);

    $listing->refresh();

    expect($listing->geocode_status)->toBe('skipped')
        ->and($listing->latitude)->toBeNull()
        ->and($listing->longitude)->toBeNull();
});

it('marks listing as failed when geocoding returns null', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'address' => 'Invalid Address',
            'city' => 'Unknown City',
        ],
        'geocode_status' => null,
    ]);

    $mockService = Mockery::mock(GeocodingService::class);
    $mockService->shouldReceive('geocode')
        ->once()
        ->andReturn(null);

    $job = new GeocodeListingJob($listing->id);
    $job->handle($mockService);

    $listing->refresh();

    expect($listing->geocode_status)->toBe('failed')
        ->and($listing->latitude)->toBeNull()
        ->and($listing->longitude)->toBeNull();
});

it('skips listing already geocoded', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => ['address' => 'Test', 'city' => 'Test'],
        'geocode_status' => 'success',
        'latitude' => 20.123,
        'longitude' => -103.456,
    ]);

    $mockService = Mockery::mock(GeocodingService::class);
    $mockService->shouldNotReceive('geocode');

    $job = new GeocodeListingJob($listing->id);
    $job->handle($mockService);

    $listing->refresh();

    // Should remain unchanged
    expect($listing->geocode_status)->toBe('success')
        ->and((float) $listing->latitude)->toBe(20.123)
        ->and((float) $listing->longitude)->toBe(-103.456);
});

it('handles non-existent listing gracefully', function () {
    $mockService = Mockery::mock(GeocodingService::class);
    $mockService->shouldNotReceive('geocode');

    $job = new GeocodeListingJob(99999);
    $job->handle($mockService);

    // Should complete without error
    expect(true)->toBeTrue();
});

it('geocodes with city only when no address', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'city' => 'Guadalajara',
            'state' => 'Jalisco',
        ],
        'geocode_status' => null,
    ]);

    $mockService = Mockery::mock(GeocodingService::class);
    $mockService->shouldReceive('geocode')
        ->once()
        ->with('', 'Guadalajara', 'Jalisco')
        ->andReturn([
            'lat' => 20.65,
            'lng' => -103.35,
        ]);

    $job = new GeocodeListingJob($listing->id);
    $job->handle($mockService);

    $listing->refresh();

    expect($listing->geocode_status)->toBe('success')
        ->and($listing->latitude)->not->toBeNull();
});

it('is queued on the geocoding queue', function () {
    $job = new GeocodeListingJob(1);

    expect($job->queue)->toBe('geocoding');
});

it('has timeout and retry configuration', function () {
    $job = new GeocodeListingJob(1);

    expect($job->timeout)->toBe(30)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30, 60]);
});
