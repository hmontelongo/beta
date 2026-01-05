<?php

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Listing;
use App\Models\Property;
use App\Models\PropertyConflict;
use App\Models\PropertyVerification;
use Illuminate\Database\Eloquent\Collection;

it('can create a property', function () {
    $property = Property::factory()->create();

    expect($property)->toBeInstanceOf(Property::class)
        ->and($property->id)->not->toBeNull()
        ->and($property->address)->not->toBeEmpty()
        ->and($property->colonia)->not->toBeEmpty()
        ->and($property->city)->not->toBeEmpty()
        ->and($property->state)->toBe('Jalisco');
});

it('has many listings', function () {
    $property = Property::factory()->create();
    $listing = Listing::factory()->for($property)->create();

    expect($property->listings)->toBeInstanceOf(Collection::class)
        ->and($property->listings)->toHaveCount(1)
        ->and($property->listings->first()->id)->toBe($listing->id);
});

it('has many conflicts', function () {
    $property = Property::factory()->create();
    $conflict = PropertyConflict::factory()->for($property)->create();

    expect($property->conflicts)->toBeInstanceOf(Collection::class)
        ->and($property->conflicts)->toHaveCount(1)
        ->and($property->conflicts->first()->id)->toBe($conflict->id);
});

it('has many verifications', function () {
    $property = Property::factory()->create();
    $verification = PropertyVerification::factory()->for($property)->create();

    expect($property->verifications)->toBeInstanceOf(Collection::class)
        ->and($property->verifications)->toHaveCount(1)
        ->and($property->verifications->first()->id)->toBe($verification->id);
});

it('casts amenities to array', function () {
    $amenities = ['pool', 'gym', 'security_24h'];

    $property = Property::factory()->create(['amenities' => $amenities]);

    $property->refresh();

    expect($property->amenities)->toBeArray()
        ->and($property->amenities)->toContain('pool')
        ->and($property->amenities)->toContain('gym');
});

it('casts property_type to enum', function () {
    $property = Property::factory()->create(['property_type' => PropertyType::Apartment]);

    $property->refresh();

    expect($property->property_type)->toBeInstanceOf(PropertyType::class)
        ->and($property->property_type)->toBe(PropertyType::Apartment);
});

it('casts status to enum', function () {
    $property = Property::factory()->create(['status' => PropertyStatus::Verified]);

    $property->refresh();

    expect($property->status)->toBeInstanceOf(PropertyStatus::class)
        ->and($property->status)->toBe(PropertyStatus::Verified);
});

it('defaults to Unverified status', function () {
    $property = Property::factory()->create();

    expect($property->status)->toBe(PropertyStatus::Unverified);
});

it('can be marked as verified', function () {
    $property = Property::factory()->verified()->create();

    expect($property->status)->toBe(PropertyStatus::Verified);
});

it('can be created as apartment', function () {
    $property = Property::factory()->apartment()->create();

    expect($property->property_type)->toBe(PropertyType::Apartment);
});

it('can be created as house', function () {
    $property = Property::factory()->house()->create();

    expect($property->property_type)->toBe(PropertyType::House);
});

it('can have coordinates', function () {
    $property = Property::factory()->withCoordinates()->create();

    expect($property->latitude)->not->toBeNull()
        ->and($property->longitude)->not->toBeNull()
        ->and((float) $property->latitude)->toBeGreaterThanOrEqual(20.6)
        ->and((float) $property->latitude)->toBeLessThanOrEqual(20.7);
});

it('can have amenities using state', function () {
    $property = Property::factory()->withAmenities()->create();

    expect($property->amenities)->toBeArray();
});
