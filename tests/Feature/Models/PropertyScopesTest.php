<?php

use App\Enums\OperationType;
use App\Enums\PropertySourceType;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Helper to check if we're using SQLite (tests use in-memory SQLite).
 * Some JSON queries behave differently in SQLite vs MySQL.
 */
function usingSqlite(): bool
{
    return config('database.default') === 'sqlite';
}

describe('scopeNative', function () {
    it('returns only native properties', function () {
        Property::factory()->native($this->user)->create();
        Property::factory()->create(['source_type' => PropertySourceType::Scraped]);

        $native = Property::native()->get();

        expect($native)->toHaveCount(1);
        expect($native->first()->source_type)->toBe(PropertySourceType::Native);
    });
});

describe('scopeScraped', function () {
    it('returns only scraped properties', function () {
        Property::factory()->native($this->user)->create();
        Property::factory()->create(['source_type' => PropertySourceType::Scraped]);

        $scraped = Property::scraped()->get();

        expect($scraped)->toHaveCount(1);
        expect($scraped->first()->source_type)->toBe(PropertySourceType::Scraped);
    });
});

describe('scopeVisibleTo', function () {
    it('returns all scraped properties', function () {
        Property::factory()->count(2)->create(['source_type' => PropertySourceType::Scraped]);

        $visible = Property::visibleTo($this->user->id)->get();

        expect($visible)->toHaveCount(2);
    });

    it('returns native collaborative properties', function () {
        Property::factory()->native($this->user)->collaborative()->create();

        $otherUser = User::factory()->create();
        $visible = Property::visibleTo($otherUser->id)->get();

        expect($visible)->toHaveCount(1);
    });

    it('returns native properties owned by the user', function () {
        Property::factory()->native($this->user)->create(['is_collaborative' => false]);

        $visible = Property::visibleTo($this->user->id)->get();

        expect($visible)->toHaveCount(1);
    });

    it('does not return non-collaborative native properties owned by others', function () {
        $otherUser = User::factory()->create();
        Property::factory()->native($otherUser)->create(['is_collaborative' => false]);

        $visible = Property::visibleTo($this->user->id)->get();

        expect($visible)->toHaveCount(0);
    });
});

describe('scopeFilterByOperationType', function () {
    it('filters native properties by operation type', function () {
        Property::factory()->native($this->user)->forRent()->create();
        Property::factory()->native($this->user)->forSale()->create();

        $rentals = Property::filterByOperationType('rent')->get();

        expect($rentals)->toHaveCount(1);
        expect($rentals->first()->operation_type)->toBe(OperationType::Rent);
    });

    it('filters scraped properties by operation type in listings', function () {
        // Skip on SQLite - JSON queries behave differently
        if (usingSqlite()) {
            $this->markTestSkipped('JSON queries require MySQL');
        }

        $rentProperty = Property::factory()->create(['source_type' => PropertySourceType::Scraped]);
        Listing::factory()->for($rentProperty)->create([
            'operations' => [['type' => 'rent', 'price' => 25000]],
        ]);

        $saleProperty = Property::factory()->create(['source_type' => PropertySourceType::Scraped]);
        Listing::factory()->for($saleProperty)->create([
            'operations' => [['type' => 'sale', 'price' => 5000000]],
        ]);

        $rentals = Property::filterByOperationType('rent')->get();

        expect($rentals)->toHaveCount(1);
        expect($rentals->first()->id)->toBe($rentProperty->id);
    });

    it('returns both native and scraped properties matching operation type', function () {
        // Skip on SQLite - JSON queries behave differently
        if (usingSqlite()) {
            $this->markTestSkipped('JSON queries require MySQL');
        }

        // Native rental
        Property::factory()->native($this->user)->forRent()->create();

        // Scraped rental
        $scrapedRental = Property::factory()->create(['source_type' => PropertySourceType::Scraped]);
        Listing::factory()->for($scrapedRental)->create([
            'operations' => [['type' => 'rent', 'price' => 30000]],
        ]);

        // Native sale (should not be included)
        Property::factory()->native($this->user)->forSale()->create();

        $rentals = Property::filterByOperationType('rent')->get();

        expect($rentals)->toHaveCount(2);
    });
});

describe('scopeFilterByPriceRange', function () {
    it('filters native properties by min price', function () {
        Property::factory()->native($this->user)->forSale()->create(['price' => 3000000]);
        Property::factory()->native($this->user)->forSale()->create(['price' => 7000000]);

        $filtered = Property::filterByPriceRange(minPrice: 5000000)->get();

        expect($filtered)->toHaveCount(1);
        expect((float) $filtered->first()->price)->toEqual(7000000.0);
    });

    it('filters native properties by max price', function () {
        Property::factory()->native($this->user)->forSale()->create(['price' => 3000000]);
        Property::factory()->native($this->user)->forSale()->create(['price' => 7000000]);

        $filtered = Property::filterByPriceRange(maxPrice: 5000000)->get();

        expect($filtered)->toHaveCount(1);
        expect((float) $filtered->first()->price)->toEqual(3000000.0);
    });

    it('filters native properties by price range', function () {
        Property::factory()->native($this->user)->forSale()->create(['price' => 2000000]);
        Property::factory()->native($this->user)->forSale()->create(['price' => 5000000]);
        Property::factory()->native($this->user)->forSale()->create(['price' => 10000000]);

        $filtered = Property::filterByPriceRange(minPrice: 3000000, maxPrice: 8000000)->get();

        expect($filtered)->toHaveCount(1);
        expect((float) $filtered->first()->price)->toEqual(5000000.0);
    });

    it('filters scraped properties by price in listings', function () {
        // Skip on SQLite - JSON queries behave differently
        if (usingSqlite()) {
            $this->markTestSkipped('JSON queries require MySQL');
        }

        $cheapProperty = Property::factory()->create(['source_type' => PropertySourceType::Scraped]);
        Listing::factory()->for($cheapProperty)->create([
            'operations' => [['type' => 'sale', 'price' => 2000000]],
        ]);

        $expensiveProperty = Property::factory()->create(['source_type' => PropertySourceType::Scraped]);
        Listing::factory()->for($expensiveProperty)->create([
            'operations' => [['type' => 'sale', 'price' => 10000000]],
        ]);

        $filtered = Property::filterByPriceRange(minPrice: 5000000)->get();

        expect($filtered)->toHaveCount(1);
        expect($filtered->first()->id)->toBe($expensiveProperty->id);
    });

    it('filters by operation type when provided', function () {
        Property::factory()->native($this->user)->forRent()->create(['price' => 25000]);
        Property::factory()->native($this->user)->forSale()->create(['price' => 5000000]);

        $filtered = Property::filterByPriceRange(minPrice: 20000, operationType: 'rent')->get();

        expect($filtered)->toHaveCount(1);
        expect($filtered->first()->operation_type)->toBe(OperationType::Rent);
    });
});

describe('scopeOrderByPrice', function () {
    it('orders native properties by price ascending', function () {
        Property::factory()->native($this->user)->forSale()->create(['price' => 7000000]);
        Property::factory()->native($this->user)->forSale()->create(['price' => 3000000]);
        Property::factory()->native($this->user)->forSale()->create(['price' => 5000000]);

        $ordered = Property::native()->orderByPrice('asc')->get();

        expect((float) $ordered[0]->price)->toEqual(3000000.0);
        expect((float) $ordered[1]->price)->toEqual(5000000.0);
        expect((float) $ordered[2]->price)->toEqual(7000000.0);
    });

    it('orders native properties by price descending', function () {
        Property::factory()->native($this->user)->forSale()->create(['price' => 7000000]);
        Property::factory()->native($this->user)->forSale()->create(['price' => 3000000]);
        Property::factory()->native($this->user)->forSale()->create(['price' => 5000000]);

        $ordered = Property::native()->orderByPrice('desc')->get();

        expect((float) $ordered[0]->price)->toEqual(7000000.0);
        expect((float) $ordered[1]->price)->toEqual(5000000.0);
        expect((float) $ordered[2]->price)->toEqual(3000000.0);
    });

    it('orders scraped properties by listing price', function () {
        // Skip on SQLite - JSON queries behave differently
        if (usingSqlite()) {
            $this->markTestSkipped('JSON queries require MySQL');
        }

        $cheap = Property::factory()->create(['source_type' => PropertySourceType::Scraped]);
        Listing::factory()->for($cheap)->create([
            'operations' => [['type' => 'sale', 'price' => 2000000]],
        ]);

        $expensive = Property::factory()->create(['source_type' => PropertySourceType::Scraped]);
        Listing::factory()->for($expensive)->create([
            'operations' => [['type' => 'sale', 'price' => 8000000]],
        ]);

        $ordered = Property::scraped()->orderByPrice('asc')->get();

        expect($ordered[0]->id)->toBe($cheap->id);
        expect($ordered[1]->id)->toBe($expensive->id);
    });
});

describe('scopeOwnedBy', function () {
    it('returns properties owned by the specified user', function () {
        Property::factory()->native($this->user)->create();

        $otherUser = User::factory()->create();
        Property::factory()->native($otherUser)->create();

        $owned = Property::ownedBy($this->user->id)->get();

        expect($owned)->toHaveCount(1);
        expect($owned->first()->user_id)->toBe($this->user->id);
    });
});

describe('scopeCollaborative', function () {
    it('returns only collaborative properties', function () {
        Property::factory()->native($this->user)->collaborative()->create();
        Property::factory()->native($this->user)->create(['is_collaborative' => false]);

        $collaborative = Property::collaborative()->get();

        expect($collaborative)->toHaveCount(1);
        expect($collaborative->first()->is_collaborative)->toBeTrue();
    });
});
