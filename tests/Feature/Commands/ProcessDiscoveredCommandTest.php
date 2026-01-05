<?php

use App\Enums\DiscoveredListingStatus;
use App\Jobs\ScrapeListingJob;
use App\Models\DiscoveredListing;
use App\Models\Platform;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('dispatches jobs for pending discovered listings', function () {
    $platform = Platform::factory()->create();

    DiscoveredListing::factory()->count(3)->create([
        'platform_id' => $platform->id,
        'status' => DiscoveredListingStatus::Pending,
    ]);

    $this->artisan('scrape:process')
        ->assertSuccessful()
        ->expectsOutput('Dispatched 3 listings for scraping.');

    Queue::assertPushed(ScrapeListingJob::class, 3);
});

it('respects limit option', function () {
    $platform = Platform::factory()->create();

    DiscoveredListing::factory()->count(10)->create([
        'platform_id' => $platform->id,
        'status' => DiscoveredListingStatus::Pending,
    ]);

    $this->artisan('scrape:process', ['--limit' => 5])
        ->assertSuccessful()
        ->expectsOutput('Dispatched 5 listings for scraping.');

    Queue::assertPushed(ScrapeListingJob::class, 5);
});

it('filters by platform', function () {
    $platform1 = Platform::factory()->create(['name' => 'inmuebles24']);
    $platform2 = Platform::factory()->create(['name' => 'vivanuncios']);

    DiscoveredListing::factory()->count(3)->create([
        'platform_id' => $platform1->id,
        'status' => DiscoveredListingStatus::Pending,
    ]);

    DiscoveredListing::factory()->count(2)->create([
        'platform_id' => $platform2->id,
        'status' => DiscoveredListingStatus::Pending,
    ]);

    $this->artisan('scrape:process', ['--platform' => 'inmuebles24'])
        ->assertSuccessful()
        ->expectsOutput('Dispatched 3 listings for scraping.');

    Queue::assertPushed(ScrapeListingJob::class, 3);
});

it('fails for unknown platform', function () {
    $this->artisan('scrape:process', ['--platform' => 'unknown'])
        ->assertFailed()
        ->expectsOutput("Platform 'unknown' not found.");

    Queue::assertNotPushed(ScrapeListingJob::class);
});

it('only processes pending listings', function () {
    $platform = Platform::factory()->create();

    DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'status' => DiscoveredListingStatus::Pending,
    ]);

    DiscoveredListing::factory()->scraped()->create([
        'platform_id' => $platform->id,
    ]);

    DiscoveredListing::factory()->failed()->create([
        'platform_id' => $platform->id,
    ]);

    $this->artisan('scrape:process')
        ->assertSuccessful()
        ->expectsOutput('Dispatched 1 listings for scraping.');

    Queue::assertPushed(ScrapeListingJob::class, 1);
});

it('orders by priority then created_at', function () {
    $platform = Platform::factory()->create();

    $lowPriorityOld = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'status' => DiscoveredListingStatus::Pending,
        'priority' => 0,
        'created_at' => now()->subHour(),
    ]);

    $highPriority = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'status' => DiscoveredListingStatus::Pending,
        'priority' => 10,
        'created_at' => now(),
    ]);

    $lowPriorityNew = DiscoveredListing::factory()->create([
        'platform_id' => $platform->id,
        'status' => DiscoveredListingStatus::Pending,
        'priority' => 0,
        'created_at' => now(),
    ]);

    $this->artisan('scrape:process', ['--limit' => 2])
        ->assertSuccessful();

    $dispatched = [];
    Queue::assertPushed(ScrapeListingJob::class, function ($job) use (&$dispatched) {
        $dispatched[] = $job->discoveredListingId;

        return true;
    });

    expect($dispatched[0])->toBe($highPriority->id)
        ->and($dispatched[1])->toBe($lowPriorityOld->id);
});

it('handles zero pending listings', function () {
    $this->artisan('scrape:process')
        ->assertSuccessful()
        ->expectsOutput('Dispatched 0 listings for scraping.');

    Queue::assertNotPushed(ScrapeListingJob::class);
});
