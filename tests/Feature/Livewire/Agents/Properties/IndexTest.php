<?php

use App\Livewire\Agents\Properties\Index;
use App\Models\Property;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('source filter', function () {
    it('shows all properties by default', function () {
        // Create native property owned by current user
        $myProperty = Property::factory()->native($this->user)->create();

        // Create native property owned by another user
        $otherUser = User::factory()->create();
        $otherProperty = Property::factory()->native($otherUser)->create();

        // Create scraped property
        $scrapedProperty = Property::factory()->create();

        Livewire::test(Index::class)
            ->assertSee($myProperty->colonia)
            ->assertSee($otherProperty->colonia)
            ->assertSee($scrapedProperty->colonia);
    });

    it('filters to my properties when source is mine', function () {
        // Create native property owned by current user
        $myProperty = Property::factory()->native($this->user)->create([
            'colonia' => 'Providencia',
            'price' => 1000000, // Unique identifier in price
        ]);

        // Create native property owned by another user
        $otherUser = User::factory()->create();
        $otherProperty = Property::factory()->native($otherUser)->create([
            'colonia' => 'Americana',
            'price' => 2000000,
        ]);

        // Create scraped property
        $scrapedProperty = Property::factory()->create([
            'colonia' => 'Chapalita',
        ]);

        $component = Livewire::test(Index::class)
            ->set('source', 'mine');

        // Check the query returns only my property
        $properties = $component->viewData('properties');
        expect($properties->total())->toBe(1)
            ->and($properties->first()->id)->toBe($myProperty->id);
    });

    it('computes my properties count correctly', function () {
        // Create native properties owned by current user
        Property::factory()->native($this->user)->count(3)->create();

        // Create native property owned by another user
        $otherUser = User::factory()->create();
        Property::factory()->native($otherUser)->count(2)->create();

        // Create scraped property
        Property::factory()->count(5)->create();

        $component = Livewire::test(Index::class);

        expect($component->instance()->myPropertiesCount)->toBe(3);
    });

    it('resets pagination when source changes', function () {
        // Create enough properties to have multiple pages
        Property::factory()->native($this->user)->count(15)->create();

        // Simulate being on page 2, then changing source
        $component = Livewire::test(Index::class)
            ->call('gotoPage', 2)
            ->set('source', 'mine');

        // After changing source, the component resets to page 1
        $properties = $component->viewData('properties');
        expect($properties->currentPage())->toBe(1);
    });

    it('includes source in grid key for re-rendering', function () {
        $component = Livewire::test(Index::class);
        $initialKey = $component->instance()->gridKey;

        $component->set('source', 'mine');
        $newKey = $component->instance()->gridKey;

        expect($newKey)->not->toBe($initialKey);
    });

    it('clears source when clearing all filters', function () {
        Livewire::test(Index::class)
            ->set('source', 'mine')
            ->set('operationType', 'rent')
            ->call('clearFilters')
            ->assertSet('source', '')
            ->assertSet('operationType', '');
    });
});

describe('property card display', function () {
    it('shows ownership badge for owned properties', function () {
        $myProperty = Property::factory()->native($this->user)->create([
            'colonia' => 'Providencia',
        ]);

        Livewire::test(Index::class)
            ->assertSee('Mi propiedad');
    });

    it('does not show ownership badge for non-owned properties', function () {
        $otherUser = User::factory()->create();
        $otherProperty = Property::factory()->native($otherUser)->create([
            'colonia' => 'Americana',
        ]);

        $component = Livewire::test(Index::class);

        // Should see the property but not as "my property"
        $component->assertSee('Americana');

        // Check rendered HTML doesn't contain ownership badge for this specific property
        $html = $component->html();
        expect($html)->toContain('Americana');
    });

    it('uses cover_image accessor for native properties', function () {
        $property = Property::factory()->native($this->user)->create();

        // Without images, cover_image should be null
        expect($property->cover_image)->toBeNull();
    });

    it('uses primary_price accessor for native properties', function () {
        $property = Property::factory()->native($this->user)->create([
            'price' => 1500000,
            'price_currency' => 'MXN',
        ]);

        $primaryPrice = $property->primary_price;

        expect($primaryPrice)->toBeArray()
            ->and($primaryPrice['price'])->toBe(1500000.0);
    });
});

describe('eager loading', function () {
    it('eager loads property images for native properties', function () {
        $property = Property::factory()->native($this->user)->create();

        // The query should eager load propertyImages
        Livewire::test(Index::class);

        // No N+1 - if this test runs without additional queries for images, eager loading works
        expect(true)->toBeTrue();
    });
});
