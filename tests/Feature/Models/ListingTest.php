<?php

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\ListingPhone;
use App\Models\Platform;
use App\Models\Property;
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

it('belongs to an agent (nullable)', function () {
    $agent = Agent::factory()->create();
    $listing = Listing::factory()->create(['agent_id' => $agent->id]);

    expect($listing->agent)->toBeInstanceOf(Agent::class)
        ->and($listing->agent->id)->toBe($agent->id);
});

it('belongs to an agency (nullable)', function () {
    $agency = Agency::factory()->create();
    $listing = Listing::factory()->create(['agency_id' => $agency->id]);

    expect($listing->agency)->toBeInstanceOf(Agency::class)
        ->and($listing->agency->id)->toBe($agency->id);
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

it('can be created with agent using state', function () {
    $listing = Listing::factory()->withAgent()->create();

    expect($listing->agent_id)->not->toBeNull()
        ->and($listing->agent)->toBeInstanceOf(Agent::class);
});

it('can be created with agency using state', function () {
    $listing = Listing::factory()->withAgency()->create();

    expect($listing->agency_id)->not->toBeNull()
        ->and($listing->agency)->toBeInstanceOf(Agency::class);
});

it('can be created with quality issues', function () {
    $listing = Listing::factory()->withQualityIssues()->create();

    expect($listing->data_quality)->toBeArray()
        ->and($listing->data_quality)->toHaveKey('missing')
        ->and($listing->data_quality)->toHaveKey('suspect')
        ->and($listing->data_quality)->toHaveKey('zero_values');
});
