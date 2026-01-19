<?php

use App\Jobs\GeocodeListingJob;
use App\Jobs\ProcessGeocodingBatchJob;
use App\Models\Listing;
use App\Models\Platform;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->platform = Platform::factory()->create();
});

it('dispatches geocoding jobs for listings without geocode_status', function () {
    Queue::fake();

    // Create listings needing geocoding
    $listing1 = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => null,
    ]);
    $listing2 = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => null,
    ]);

    // Create listing already geocoded (should be skipped)
    $listing3 = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => 'success',
    ]);

    $job = new ProcessGeocodingBatchJob;
    $job->handle();

    Queue::assertPushed(GeocodeListingJob::class, 2);
    Queue::assertPushed(GeocodeListingJob::class, fn ($job) => $job->listingId === $listing1->id);
    Queue::assertPushed(GeocodeListingJob::class, fn ($job) => $job->listingId === $listing2->id);
});

it('respects batch size configuration', function () {
    Queue::fake();

    // Create 5 listings needing geocoding
    Listing::factory()->count(5)->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => null,
    ]);

    // Process with batch size of 2
    $job = new ProcessGeocodingBatchJob(batchSize: 2);
    $job->handle();

    Queue::assertPushed(GeocodeListingJob::class, 2);
});

it('does nothing when no listings need geocoding', function () {
    Queue::fake();

    // Create only geocoded listings
    Listing::factory()->count(3)->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => 'success',
    ]);

    $job = new ProcessGeocodingBatchJob;
    $job->handle();

    Queue::assertNotPushed(GeocodeListingJob::class);
});

it('skips listings with failed or skipped geocode_status', function () {
    Queue::fake();

    Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => 'failed',
    ]);
    Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => 'skipped',
    ]);
    $needsGeocoding = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => null,
    ]);

    $job = new ProcessGeocodingBatchJob;
    $job->handle();

    Queue::assertPushed(GeocodeListingJob::class, 1);
    Queue::assertPushed(GeocodeListingJob::class, fn ($job) => $job->listingId === $needsGeocoding->id);
});

it('processes oldest listings first', function () {
    Queue::fake();

    $older = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => null,
        'created_at' => now()->subDays(2),
    ]);
    $newer = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => null,
        'created_at' => now(),
    ]);

    // Process with batch size of 1 to check order
    $job = new ProcessGeocodingBatchJob(batchSize: 1);
    $job->handle();

    Queue::assertPushed(GeocodeListingJob::class, 1);
    Queue::assertPushed(GeocodeListingJob::class, fn ($job) => $job->listingId === $older->id);
});

it('does nothing when geocoding is disabled', function () {
    Queue::fake();
    config(['services.geocoding.enabled' => false]);

    Listing::factory()->count(3)->create([
        'platform_id' => $this->platform->id,
        'geocode_status' => null,
    ]);

    $job = new ProcessGeocodingBatchJob;
    $job->handle();

    Queue::assertNotPushed(GeocodeListingJob::class);
});

it('is queued on the geocoding queue', function () {
    $job = new ProcessGeocodingBatchJob;

    expect($job->queue)->toBe('geocoding');
});

it('has timeout configuration', function () {
    $job = new ProcessGeocodingBatchJob;

    expect($job->timeout)->toBe(60)
        ->and($job->tries)->toBe(1);
});
