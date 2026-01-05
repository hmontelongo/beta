<?php

use App\Enums\PhoneType;
use App\Models\Listing;
use App\Models\ListingPhone;

it('can create a listing phone', function () {
    $listingPhone = ListingPhone::factory()->create();

    expect($listingPhone)->toBeInstanceOf(ListingPhone::class)
        ->and($listingPhone->id)->not->toBeNull()
        ->and($listingPhone->phone)->not->toBeEmpty();
});

it('belongs to a listing', function () {
    $listing = Listing::factory()->create();
    $listingPhone = ListingPhone::factory()->for($listing)->create();

    expect($listingPhone->listing)->toBeInstanceOf(Listing::class)
        ->and($listingPhone->listing->id)->toBe($listing->id);
});

it('casts phone_type to enum', function () {
    $listingPhone = ListingPhone::factory()->create(['phone_type' => PhoneType::Whatsapp]);

    $listingPhone->refresh();

    expect($listingPhone->phone_type)->toBeInstanceOf(PhoneType::class)
        ->and($listingPhone->phone_type)->toBe(PhoneType::Whatsapp);
});

it('cascade deletes when listing is deleted', function () {
    $listing = Listing::factory()->create();
    $listingPhone = ListingPhone::factory()->for($listing)->create();

    $phoneId = $listingPhone->id;

    $listing->delete();

    expect(ListingPhone::find($phoneId))->toBeNull();
});

it('can be created as whatsapp', function () {
    $listingPhone = ListingPhone::factory()->whatsapp()->create();

    expect($listingPhone->phone_type)->toBe(PhoneType::Whatsapp);
});

it('can be created as mobile', function () {
    $listingPhone = ListingPhone::factory()->mobile()->create();

    expect($listingPhone->phone_type)->toBe(PhoneType::Mobile);
});

it('can be created as landline', function () {
    $listingPhone = ListingPhone::factory()->landline()->create();

    expect($listingPhone->phone_type)->toBe(PhoneType::Landline);
});

it('allows nullable contact_name', function () {
    $listingPhone = ListingPhone::factory()->create(['contact_name' => null]);

    expect($listingPhone->contact_name)->toBeNull();
});

it('stores Mexican phone format correctly', function () {
    $listingPhone = ListingPhone::factory()->create();

    expect($listingPhone->phone)->toStartWith('+5233');
});
