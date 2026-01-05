<?php

use App\Models\Listing;
use App\Models\Property;
use App\Models\PropertyConflict;

it('can create a property conflict', function () {
    $conflict = PropertyConflict::factory()->create();

    expect($conflict)->toBeInstanceOf(PropertyConflict::class)
        ->and($conflict->id)->not->toBeNull()
        ->and($conflict->field)->not->toBeEmpty();
});

it('belongs to a property', function () {
    $property = Property::factory()->create();
    $conflict = PropertyConflict::factory()->for($property)->create();

    expect($conflict->property)->toBeInstanceOf(Property::class)
        ->and($conflict->property->id)->toBe($property->id);
});

it('belongs to a listing', function () {
    $listing = Listing::factory()->create();
    $conflict = PropertyConflict::factory()->create(['listing_id' => $listing->id]);

    expect($conflict->listing)->toBeInstanceOf(Listing::class)
        ->and($conflict->listing->id)->toBe($listing->id);
});

it('casts resolution to array', function () {
    $resolution = [
        'resolved_by' => 'admin',
        'chosen_value' => '3',
        'notes' => 'Manual review',
    ];

    $conflict = PropertyConflict::factory()->create(['resolution' => $resolution]);

    $conflict->refresh();

    expect($conflict->resolution)->toBeArray()
        ->and($conflict->resolution['resolved_by'])->toBe('admin')
        ->and($conflict->resolution['chosen_value'])->toBe('3');
});

it('casts resolved to boolean', function () {
    $conflict = PropertyConflict::factory()->create(['resolved' => true]);

    $conflict->refresh();

    expect($conflict->resolved)->toBeBool()
        ->and($conflict->resolved)->toBeTrue();
});

it('casts resolved_at to datetime', function () {
    $conflict = PropertyConflict::factory()->resolved()->create();

    expect($conflict->resolved_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('defaults to unresolved', function () {
    $conflict = PropertyConflict::factory()->create();

    expect($conflict->resolved)->toBeFalse()
        ->and($conflict->resolution)->toBeNull()
        ->and($conflict->resolved_at)->toBeNull();
});

it('can be created as resolved', function () {
    $conflict = PropertyConflict::factory()->resolved()->create();

    expect($conflict->resolved)->toBeTrue()
        ->and($conflict->resolution)->toBeArray()
        ->and($conflict->resolved_at)->not->toBeNull();
});

it('stores canonical and source values', function () {
    $conflict = PropertyConflict::factory()->create([
        'canonical_value' => '3',
        'source_value' => '4',
    ]);

    expect($conflict->canonical_value)->toBe('3')
        ->and($conflict->source_value)->toBe('4');
});
