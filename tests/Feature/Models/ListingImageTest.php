<?php

use App\Models\Listing;
use App\Models\ListingImage;

it('can create a listing image', function () {
    $listingImage = ListingImage::factory()->create();

    expect($listingImage)->toBeInstanceOf(ListingImage::class)
        ->and($listingImage->id)->not->toBeNull()
        ->and($listingImage->url)->not->toBeEmpty();
});

it('belongs to a listing', function () {
    $listing = Listing::factory()->create();
    $listingImage = ListingImage::factory()->for($listing)->create();

    expect($listingImage->listing)->toBeInstanceOf(Listing::class)
        ->and($listingImage->listing->id)->toBe($listing->id);
});

it('cascade deletes when listing is deleted', function () {
    $listing = Listing::factory()->create();
    $listingImage = ListingImage::factory()->for($listing)->create();

    $imageId = $listingImage->id;

    $listing->delete();

    expect(ListingImage::find($imageId))->toBeNull();
});

it('has position ordering', function () {
    $listing = Listing::factory()->create();

    $image1 = ListingImage::factory()->for($listing)->create(['position' => 0]);
    $image2 = ListingImage::factory()->for($listing)->create(['position' => 1]);
    $image3 = ListingImage::factory()->for($listing)->create(['position' => 2]);

    $images = $listing->images()->orderBy('position')->get();

    expect($images[0]->id)->toBe($image1->id)
        ->and($images[1]->id)->toBe($image2->id)
        ->and($images[2]->id)->toBe($image3->id);
});

it('allows nullable local_path', function () {
    $listingImage = ListingImage::factory()->create(['local_path' => null]);

    expect($listingImage->local_path)->toBeNull();
});

it('allows nullable hash', function () {
    $listingImage = ListingImage::factory()->create(['hash' => null]);

    expect($listingImage->hash)->toBeNull();
});

it('can be created as downloaded', function () {
    $listingImage = ListingImage::factory()->downloaded()->create();

    expect($listingImage->local_path)->not->toBeNull()
        ->and($listingImage->hash)->not->toBeNull()
        ->and($listingImage->local_path)->toContain('images/listings/');
});

it('can be created at specific position', function () {
    $listingImage = ListingImage::factory()->atPosition(5)->create();

    expect($listingImage->position)->toBe(5);
});
