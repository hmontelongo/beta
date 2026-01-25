<?php

use App\Enums\OperationType;
use App\Enums\PropertySourceType;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;

describe('Native Property Creation', function () {
    it('creates a native property with owner', function () {
        $user = User::factory()->create();
        $property = Property::factory()->native($user)->create();

        expect($property->source_type)->toBe(PropertySourceType::Native)
            ->and($property->user_id)->toBe($user->id)
            ->and($property->owner->id)->toBe($user->id);
    });

    it('creates a native property with price and operation type', function () {
        $property = Property::factory()->forSale()->create();

        expect($property->source_type)->toBe(PropertySourceType::Native)
            ->and($property->operation_type)->toBe(OperationType::Sale)
            ->and($property->price)->toBeGreaterThan(0);
    });

    it('creates a collaborative property', function () {
        $property = Property::factory()->collaborative()->create();

        expect($property->source_type)->toBe(PropertySourceType::Native)
            ->and($property->is_collaborative)->toBeTrue()
            ->and($property->commission_split)->toBeGreaterThan(0);
    });

    it('defaults source_type to scraped', function () {
        $property = Property::factory()->create();

        expect($property->source_type)->toBe(PropertySourceType::Scraped)
            ->and($property->user_id)->toBeNull();
    });
});

describe('Property Scopes', function () {
    it('scopes to scraped properties only', function () {
        Property::factory()->count(2)->create(); // scraped
        Property::factory()->native()->count(3)->create(); // native

        $scraped = Property::scraped()->get();

        expect($scraped)->toHaveCount(2)
            ->and($scraped->every(fn ($p) => $p->source_type === PropertySourceType::Scraped))->toBeTrue();
    });

    it('scopes to native properties only', function () {
        Property::factory()->count(2)->create(); // scraped
        Property::factory()->native()->count(3)->create(); // native

        $native = Property::native()->get();

        expect($native)->toHaveCount(3)
            ->and($native->every(fn ($p) => $p->source_type === PropertySourceType::Native))->toBeTrue();
    });

    it('scopes to properties owned by a user', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Property::factory()->native($user)->count(2)->create();
        Property::factory()->native($other)->count(3)->create();

        $owned = Property::ownedBy($user->id)->get();

        expect($owned)->toHaveCount(2)
            ->and($owned->every(fn ($p) => $p->user_id === $user->id))->toBeTrue();
    });

    it('scopes to collaborative properties', function () {
        Property::factory()->native()->count(2)->create(); // private native
        Property::factory()->collaborative()->count(3)->create(); // collaborative

        $collaborative = Property::collaborative()->get();

        expect($collaborative)->toHaveCount(3)
            ->and($collaborative->every(fn ($p) => $p->is_collaborative === true))->toBeTrue();
    });

    it('scopes visible properties for a user', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        // Scraped properties (visible to all)
        Property::factory()->count(2)->create();

        // User's own native properties (visible to owner)
        Property::factory()->native($user)->count(2)->create();

        // Other's private native properties (NOT visible to user)
        Property::factory()->native($other)->count(2)->create();

        // Other's collaborative properties (visible to all)
        Property::factory()->collaborative($other)->count(3)->create();

        $visible = Property::visibleTo($user->id)->get();

        // Should see: 2 scraped + 2 own native + 3 collaborative = 7
        expect($visible)->toHaveCount(7);
    });

    it('hides other users private native properties', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        // Other's private native property
        $privateProperty = Property::factory()->native($other)->create([
            'is_collaborative' => false,
        ]);

        $visible = Property::visibleTo($user->id)->get();

        expect($visible->contains($privateProperty))->toBeFalse();
    });
});

describe('Property Helper Methods', function () {
    it('identifies native properties', function () {
        $native = Property::factory()->native()->create();
        $scraped = Property::factory()->create();

        expect($native->isNative())->toBeTrue()
            ->and($native->isScraped())->toBeFalse()
            ->and($scraped->isNative())->toBeFalse()
            ->and($scraped->isScraped())->toBeTrue();
    });

    it('checks ownership correctly', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $property = Property::factory()->native($user)->create();

        expect($property->isOwnedBy($user))->toBeTrue()
            ->and($property->isOwnedBy($other))->toBeFalse()
            ->and($property->isOwnedBy(null))->toBeFalse();
    });

    it('returns false for ownership on scraped properties', function () {
        $user = User::factory()->create();
        $property = Property::factory()->create(); // scraped, no owner

        expect($property->isOwnedBy($user))->toBeFalse();
    });
});

describe('Native Property Price Accessor', function () {
    it('returns direct price for native properties', function () {
        $property = Property::factory()->forSale()->create([
            'price' => 5000000.00,
            'price_currency' => 'MXN',
        ]);

        $price = $property->primary_price;

        expect($price)->not->toBeNull()
            ->and($price['price'])->toBe(5000000.0)
            ->and($price['type'])->toBe('sale')
            ->and($price['currency'])->toBe('MXN');
    });

    it('returns rent price with correct type', function () {
        $property = Property::factory()->forRent()->create([
            'price' => 25000.00,
        ]);

        $price = $property->primary_price;

        expect($price['type'])->toBe('rent');
    });
});

describe('Property Images Relationship', function () {
    it('has many property images', function () {
        $property = Property::factory()->native()->create();
        PropertyImage::factory()->count(3)->create(['property_id' => $property->id]);

        expect($property->propertyImages)->toHaveCount(3);
    });

    it('orders property images by position', function () {
        $property = Property::factory()->native()->create();

        PropertyImage::factory()->create(['property_id' => $property->id, 'position' => 2]);
        PropertyImage::factory()->create(['property_id' => $property->id, 'position' => 0]);
        PropertyImage::factory()->create(['property_id' => $property->id, 'position' => 1]);

        $positions = $property->propertyImages->pluck('position')->toArray();

        expect($positions)->toBe([0, 1, 2]);
    });

    it('gets images from propertyImages for native properties', function () {
        $property = Property::factory()->native()->create();
        PropertyImage::factory()->count(2)->create(['property_id' => $property->id]);

        $images = $property->images;

        expect($images)->toHaveCount(2);
    });

    it('gets cover image from propertyImages for native properties', function () {
        $property = Property::factory()->native()->create();

        PropertyImage::factory()->create([
            'property_id' => $property->id,
            'is_cover' => false,
            'position' => 1,
        ]);

        $cover = PropertyImage::factory()->cover()->create([
            'property_id' => $property->id,
        ]);

        expect($property->cover_image)->toBe($cover->url);
    });

    it('falls back to first image when no cover is set', function () {
        $property = Property::factory()->native()->create();

        $first = PropertyImage::factory()->create([
            'property_id' => $property->id,
            'position' => 0,
        ]);

        PropertyImage::factory()->create([
            'property_id' => $property->id,
            'position' => 1,
        ]);

        expect($property->cover_image)->toBe($first->url);
    });
});

describe('Property Soft Deletes', function () {
    it('soft deletes a property', function () {
        $property = Property::factory()->native()->create();
        $id = $property->id;

        $property->delete();

        expect(Property::find($id))->toBeNull()
            ->and(Property::withTrashed()->find($id))->not->toBeNull();
    });

    it('excludes soft deleted properties from queries', function () {
        Property::factory()->native()->count(2)->create();
        $deleted = Property::factory()->native()->create();
        $deleted->delete();

        expect(Property::native()->count())->toBe(2);
    });

    it('can restore soft deleted properties', function () {
        $property = Property::factory()->native()->create();
        $id = $property->id;

        $property->delete();
        expect(Property::find($id))->toBeNull();

        Property::withTrashed()->find($id)->restore();
        expect(Property::find($id))->not->toBeNull();
    });
});

describe('Location Display Accessor', function () {
    it('formats location with colonia and city', function () {
        $property = Property::factory()->create([
            'colonia' => 'Providencia',
            'city' => 'Guadalajara',
        ]);

        expect($property->location_display)->toBe('Providencia, Guadalajara');
    });

    it('handles empty colonia', function () {
        $property = Property::factory()->create([
            'colonia' => '',
            'city' => 'Guadalajara',
        ]);

        expect($property->location_display)->toBe('Guadalajara');
    });

    it('handles empty city', function () {
        $property = Property::factory()->create([
            'colonia' => 'Providencia',
            'city' => '',
        ]);

        expect($property->location_display)->toBe('Providencia');
    });
});
