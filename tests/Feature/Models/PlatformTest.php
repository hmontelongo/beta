<?php

use App\Models\Listing;
use App\Models\Platform;
use App\Models\ScrapeJob;
use Illuminate\Database\Eloquent\Collection;

it('can create a platform', function () {
    $platform = Platform::factory()->create();

    expect($platform)->toBeInstanceOf(Platform::class)
        ->and($platform->id)->not->toBeNull()
        ->and($platform->name)->not->toBeEmpty()
        ->and($platform->base_url)->not->toBeEmpty();
});

it('has many listings', function () {
    $platform = Platform::factory()->create();
    $listing = Listing::factory()->for($platform)->create();

    expect($platform->listings)->toBeInstanceOf(Collection::class)
        ->and($platform->listings)->toHaveCount(1)
        ->and($platform->listings->first()->id)->toBe($listing->id);
});

it('has many scrape jobs', function () {
    $platform = Platform::factory()->create();
    $scrapeJob = ScrapeJob::factory()->for($platform)->create();

    expect($platform->scrapeJobs)->toBeInstanceOf(Collection::class)
        ->and($platform->scrapeJobs)->toHaveCount(1)
        ->and($platform->scrapeJobs->first()->id)->toBe($scrapeJob->id);
});

it('casts is_active to boolean', function () {
    $platform = Platform::factory()->create(['is_active' => 1]);

    $platform->refresh();

    expect($platform->is_active)->toBeBool()
        ->and($platform->is_active)->toBeTrue();
});

it('can be marked as inactive', function () {
    $platform = Platform::factory()->inactive()->create();

    expect($platform->is_active)->toBeFalse();
});

it('enforces unique name constraint', function () {
    Platform::factory()->create(['name' => 'unique-platform']);

    Platform::factory()->create(['name' => 'unique-platform']);
})->throws(\Illuminate\Database\QueryException::class);
