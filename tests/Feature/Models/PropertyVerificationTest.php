<?php

use App\Enums\VerificationStatus;
use App\Models\Property;
use App\Models\PropertyVerification;

it('can create a property verification', function () {
    $verification = PropertyVerification::factory()->create();

    expect($verification)->toBeInstanceOf(PropertyVerification::class)
        ->and($verification->id)->not->toBeNull()
        ->and($verification->phone)->not->toBeEmpty()
        ->and($verification->message_sent)->not->toBeEmpty();
});

it('belongs to a property', function () {
    $property = Property::factory()->create();
    $verification = PropertyVerification::factory()->for($property)->create();

    expect($verification->property)->toBeInstanceOf(Property::class)
        ->and($verification->property->id)->toBe($property->id);
});

it('casts status to enum', function () {
    $verification = PropertyVerification::factory()->create(['status' => VerificationStatus::Sent]);

    $verification->refresh();

    expect($verification->status)->toBeInstanceOf(VerificationStatus::class)
        ->and($verification->status)->toBe(VerificationStatus::Sent);
});

it('casts response_parsed to array', function () {
    $responseParsed = [
        'available' => true,
        'notes' => 'Property is available',
    ];

    $verification = PropertyVerification::factory()->create(['response_parsed' => $responseParsed]);

    $verification->refresh();

    expect($verification->response_parsed)->toBeArray()
        ->and($verification->response_parsed['available'])->toBeTrue()
        ->and($verification->response_parsed['notes'])->toBe('Property is available');
});

it('casts message_sent_at to datetime', function () {
    $verification = PropertyVerification::factory()->sent()->create();

    expect($verification->message_sent_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('casts response_at to datetime', function () {
    $verification = PropertyVerification::factory()->responded()->create();

    expect($verification->response_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('defaults to Pending status', function () {
    $verification = PropertyVerification::factory()->create();

    expect($verification->status)->toBe(VerificationStatus::Pending);
});

it('can be created as sent', function () {
    $verification = PropertyVerification::factory()->sent()->create();

    expect($verification->status)->toBe(VerificationStatus::Sent)
        ->and($verification->message_sent_at)->not->toBeNull();
});

it('can be created as responded', function () {
    $verification = PropertyVerification::factory()->responded()->create();

    expect($verification->status)->toBe(VerificationStatus::Responded)
        ->and($verification->response_raw)->not->toBeNull()
        ->and($verification->response_parsed)->toBeArray()
        ->and($verification->response_at)->not->toBeNull();
});

it('can be created as no response', function () {
    $verification = PropertyVerification::factory()->noResponse()->create();

    expect($verification->status)->toBe(VerificationStatus::NoResponse);
});

it('can be created as failed', function () {
    $verification = PropertyVerification::factory()->failed()->create();

    expect($verification->status)->toBe(VerificationStatus::Failed);
});

it('stores Mexican phone format', function () {
    $verification = PropertyVerification::factory()->create();

    expect($verification->phone)->toStartWith('+5233');
});
