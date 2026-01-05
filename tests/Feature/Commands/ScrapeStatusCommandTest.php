<?php

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeJobStatus;
use App\Models\DiscoveredListing;
use App\Models\Listing;
use App\Models\Platform;
use App\Models\ScrapeJob;

it('displays discovered listings counts', function () {
    $platform = Platform::factory()->create();

    DiscoveredListing::factory()->count(5)->create([
        'platform_id' => $platform->id,
        'status' => DiscoveredListingStatus::Pending,
    ]);

    DiscoveredListing::factory()->count(3)->scraped()->create([
        'platform_id' => $platform->id,
    ]);

    DiscoveredListing::factory()->count(2)->failed()->create([
        'platform_id' => $platform->id,
    ]);

    $this->artisan('scrape:status')
        ->assertSuccessful()
        ->expectsOutput('Discovered Listings:');
});

it('displays listings total count', function () {
    $platform = Platform::factory()->create();

    Listing::factory()->count(15)->create([
        'platform_id' => $platform->id,
    ]);

    $this->artisan('scrape:status')
        ->assertSuccessful()
        ->expectsOutput('Listings: 15 total');
});

it('displays scrape jobs counts', function () {
    $platform = Platform::factory()->create();

    ScrapeJob::factory()->count(2)->create([
        'platform_id' => $platform->id,
        'status' => ScrapeJobStatus::Pending,
    ]);

    ScrapeJob::factory()->count(1)->running()->create([
        'platform_id' => $platform->id,
    ]);

    ScrapeJob::factory()->count(5)->completed()->create([
        'platform_id' => $platform->id,
    ]);

    ScrapeJob::factory()->count(1)->failed()->create([
        'platform_id' => $platform->id,
    ]);

    $this->artisan('scrape:status')
        ->assertSuccessful()
        ->expectsOutput('Scrape Jobs:');
});

it('works with empty database', function () {
    $this->artisan('scrape:status')
        ->assertSuccessful()
        ->expectsOutput('Discovered Listings:')
        ->expectsOutput('Listings: 0 total')
        ->expectsOutput('Scrape Jobs:');
});
