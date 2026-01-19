<?php

use App\Models\Listing;
use App\Models\Platform;
use App\Models\Publisher;
use App\Services\PublisherExtractionService;

beforeEach(function () {
    $this->platform = Platform::factory()->create(['slug' => 'vivanuncios', 'name' => 'Vivanuncios']);
    $this->service = new PublisherExtractionService;
});

it('extracts publisher when name is provided', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'publisher_name' => 'Test Publisher',
            'publisher_id' => '12345',
            'whatsapp' => '+523312345678',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();
    expect($listing->publisher_id)->not->toBeNull();

    $publisher = Publisher::find($listing->publisher_id);
    expect($publisher->name)->toBe('Test Publisher')
        ->and($publisher->phone)->toBe('+523312345678');
});

it('extracts publisher with only platform id and no name', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'publisher_id' => '12345',
            'whatsapp' => '+523312345678',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();
    expect($listing->publisher_id)->not->toBeNull();

    $publisher = Publisher::find($listing->publisher_id);
    expect($publisher->name)->toBe('Publisher #12345 (Vivanuncios)')
        ->and($publisher->phone)->toBe('+523312345678');
});

it('extracts publisher with only phone and no name', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'whatsapp' => '+523312345678',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();
    expect($listing->publisher_id)->not->toBeNull();

    $publisher = Publisher::find($listing->publisher_id);
    expect($publisher->name)->toBe('Publisher +523312345678')
        ->and($publisher->phone)->toBe('+523312345678');
});

it('does not extract publisher when no identifier is available', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'title' => 'Some listing',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();
    expect($listing->publisher_id)->toBeNull();
});

it('finds existing publisher by platform id', function () {
    $existingPublisher = Publisher::factory()->create([
        'name' => 'Existing Publisher',
        'platform_profiles' => [
            'vivanuncios' => ['id' => '12345'],
        ],
    ]);

    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'publisher_name' => 'Different Name',
            'publisher_id' => '12345',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();
    expect($listing->publisher_id)->toBe($existingPublisher->id);
});

it('finds existing publisher by phone', function () {
    $existingPublisher = Publisher::factory()->create([
        'name' => 'Existing Publisher',
        'phone' => '+523312345678',
    ]);

    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'publisher_name' => 'Different Name',
            'whatsapp' => '+523312345678',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();
    expect($listing->publisher_id)->toBe($existingPublisher->id);
});

it('processes unlinked listings including those with only id or phone', function () {
    // Listing with name
    $listing1 = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => ['publisher_name' => 'Named Publisher'],
    ]);

    // Listing with only ID
    $listing2 = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => ['publisher_id' => '99999'],
    ]);

    // Listing with only phone
    $listing3 = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => ['whatsapp' => '+523399999999'],
    ]);

    // Listing with nothing
    $listing4 = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => ['title' => 'No publisher data'],
    ]);

    $processed = $this->service->processUnlinkedListings(100);

    expect($processed)->toBe(3); // Only 3 have identifiers

    $listing1->refresh();
    $listing2->refresh();
    $listing3->refresh();
    $listing4->refresh();

    expect($listing1->publisher_id)->not->toBeNull()
        ->and($listing2->publisher_id)->not->toBeNull()
        ->and($listing3->publisher_id)->not->toBeNull()
        ->and($listing4->publisher_id)->toBeNull();
});
