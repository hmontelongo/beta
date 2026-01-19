<?php

use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\ListingPhone;
use App\Models\Platform;
use App\Models\Property;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Collection;

it('can create a listing', function () {
    $listing = Listing::factory()->create();

    expect($listing)->toBeInstanceOf(Listing::class)
        ->and($listing->id)->not->toBeNull()
        ->and($listing->external_id)->not->toBeEmpty()
        ->and($listing->original_url)->not->toBeEmpty();
});

it('belongs to a property', function () {
    $property = Property::factory()->create();
    $listing = Listing::factory()->for($property)->create();

    expect($listing->property)->toBeInstanceOf(Property::class)
        ->and($listing->property->id)->toBe($property->id);
});

it('belongs to a platform', function () {
    $platform = Platform::factory()->create();
    $listing = Listing::factory()->for($platform)->create();

    expect($listing->platform)->toBeInstanceOf(Platform::class)
        ->and($listing->platform->id)->toBe($platform->id);
});

it('belongs to a publisher (nullable)', function () {
    $publisher = Publisher::factory()->create();
    $listing = Listing::factory()->create(['publisher_id' => $publisher->id]);

    expect($listing->publisher)->toBeInstanceOf(Publisher::class)
        ->and($listing->publisher->id)->toBe($publisher->id);
});

it('has many phones', function () {
    $listing = Listing::factory()->create();
    $phone = ListingPhone::factory()->for($listing)->create();

    expect($listing->phones)->toBeInstanceOf(Collection::class)
        ->and($listing->phones)->toHaveCount(1)
        ->and($listing->phones->first()->id)->toBe($phone->id);
});

it('has many images', function () {
    $listing = Listing::factory()->create();
    $image = ListingImage::factory()->for($listing)->create();

    expect($listing->images)->toBeInstanceOf(Collection::class)
        ->and($listing->images)->toHaveCount(1)
        ->and($listing->images->first()->id)->toBe($image->id);
});

it('casts operations to array', function () {
    $operations = [
        ['type' => 'rent', 'price' => 15000, 'currency' => 'MXN'],
    ];

    $listing = Listing::factory()->create(['operations' => $operations]);

    $listing->refresh();

    expect($listing->operations)->toBeArray()
        ->and($listing->operations[0]['type'])->toBe('rent')
        ->and($listing->operations[0]['price'])->toBe(15000);
});

it('casts raw_data to array', function () {
    $rawData = [
        'title' => 'Test Listing',
        'description' => 'A test description',
    ];

    $listing = Listing::factory()->create(['raw_data' => $rawData]);

    $listing->refresh();

    expect($listing->raw_data)->toBeArray()
        ->and($listing->raw_data['title'])->toBe('Test Listing');
});

it('casts data_quality to array', function () {
    $dataQuality = [
        'missing' => ['latitude', 'longitude'],
        'suspect' => [],
        'zero_values' => [],
    ];

    $listing = Listing::factory()->create(['data_quality' => $dataQuality]);

    $listing->refresh();

    expect($listing->data_quality)->toBeArray()
        ->and($listing->data_quality['missing'])->toContain('latitude');
});

it('casts scraped_at to datetime', function () {
    $listing = Listing::factory()->create();

    expect($listing->scraped_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('enforces unique constraint on platform_id and external_id', function () {
    $platform = Platform::factory()->create();

    Listing::factory()->create([
        'platform_id' => $platform->id,
        'external_id' => 'unique-external-123',
    ]);

    Listing::factory()->create([
        'platform_id' => $platform->id,
        'external_id' => 'unique-external-123',
    ]);
})->throws(\Illuminate\Database\QueryException::class);

it('allows same external_id on different platforms', function () {
    $platform1 = Platform::factory()->create();
    $platform2 = Platform::factory()->create();

    $listing1 = Listing::factory()->create([
        'platform_id' => $platform1->id,
        'external_id' => 'same-external-id',
    ]);

    $listing2 = Listing::factory()->create([
        'platform_id' => $platform2->id,
        'external_id' => 'same-external-id',
    ]);

    expect($listing1->id)->not->toBe($listing2->id);
});

it('can be created for sale', function () {
    $listing = Listing::factory()->forSale()->create();

    expect($listing->operations)->toHaveCount(1)
        ->and($listing->operations[0]['type'])->toBe('sale');
});

it('can be created for rent', function () {
    $listing = Listing::factory()->forRent()->create();

    expect($listing->operations)->toHaveCount(1)
        ->and($listing->operations[0]['type'])->toBe('rent');
});

it('can be created with publisher using state', function () {
    $listing = Listing::factory()->withPublisher()->create();

    expect($listing->publisher_id)->not->toBeNull()
        ->and($listing->publisher)->toBeInstanceOf(Publisher::class);
});

it('can be created with quality issues', function () {
    $listing = Listing::factory()->withQualityIssues()->create();

    expect($listing->data_quality)->toBeArray()
        ->and($listing->data_quality)->toHaveKey('missing')
        ->and($listing->data_quality)->toHaveKey('suspect')
        ->and($listing->data_quality)->toHaveKey('zero_values');
});
