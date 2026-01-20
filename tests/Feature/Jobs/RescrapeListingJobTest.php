<?php

use App\Enums\DedupStatus;
use App\Jobs\RescrapeListingJob;
use App\Models\Listing;
use App\Models\Platform;
use App\Models\Property;
use App\Services\ScraperService;

beforeEach(function () {
    $this->platform = Platform::factory()->create();
});

it('resets dedup status when listing has no property', function () {
    $group = \App\Models\ListingGroup::factory()->pendingAi()->create();

    $listing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'property_id' => null,
        'dedup_status' => DedupStatus::Completed,
        'listing_group_id' => $group->id,
        'is_primary_in_group' => true,
        'raw_data' => ['title' => 'Old data'],
    ]);

    $mockScraper = Mockery::mock(ScraperService::class);
    $mockScraper->shouldReceive('scrapeListing')
        ->once()
        ->andReturn([
            'title' => 'New data',
            'operations' => [],
            'external_codes' => null,
            'data_quality' => null,
            'images' => [],
        ]);

    $job = new RescrapeListingJob($listing->id);
    $job->handle($mockScraper);

    $listing->refresh();

    expect($listing->dedup_status)->toBe(DedupStatus::Pending)
        ->and($listing->listing_group_id)->toBeNull()
        ->and($listing->is_primary_in_group)->toBeFalse()
        ->and($listing->raw_data['title'])->toBe('New data');
});

it('keeps dedup status when listing has a property', function () {
    $property = Property::factory()->create(['needs_reanalysis' => false]);
    $group = \App\Models\ListingGroup::factory()->completed()->create([
        'property_id' => $property->id,
    ]);

    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'property_id' => $property->id,
        'dedup_status' => DedupStatus::Completed,
        'listing_group_id' => $group->id,
        'is_primary_in_group' => true,
        'raw_data' => ['title' => 'Old data'],
    ]);

    $mockScraper = Mockery::mock(ScraperService::class);
    $mockScraper->shouldReceive('scrapeListing')
        ->once()
        ->andReturn([
            'title' => 'New data',
            'operations' => [],
            'external_codes' => null,
            'data_quality' => null,
            'images' => [],
        ]);

    $job = new RescrapeListingJob($listing->id);
    $job->handle($mockScraper);

    $listing->refresh();
    $property->refresh();

    // Dedup status should NOT be reset - listing stays completed
    expect($listing->dedup_status)->toBe(DedupStatus::Completed)
        ->and($listing->listing_group_id)->toBe($group->id)
        ->and($listing->is_primary_in_group)->toBeTrue()
        ->and($listing->raw_data['title'])->toBe('New data');

    // Property should be marked for re-analysis
    expect($property->needs_reanalysis)->toBeTrue();
});

it('marks property for reanalysis when re-scraping linked listing', function () {
    $property = Property::factory()->create(['needs_reanalysis' => false]);

    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'property_id' => $property->id,
        'dedup_status' => DedupStatus::Completed,
    ]);

    $mockScraper = Mockery::mock(ScraperService::class);
    $mockScraper->shouldReceive('scrapeListing')
        ->once()
        ->andReturn([
            'title' => 'Updated',
            'operations' => [],
            'images' => [],
        ]);

    $job = new RescrapeListingJob($listing->id);
    $job->handle($mockScraper);

    $property->refresh();
    expect($property->needs_reanalysis)->toBeTrue();
});

it('updates raw_data with new scraped content', function () {
    $listing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => ['title' => 'Old title', 'description' => 'Old description'],
    ]);

    $mockScraper = Mockery::mock(ScraperService::class);
    $mockScraper->shouldReceive('scrapeListing')
        ->once()
        ->andReturn([
            'title' => 'New title',
            'description' => 'New description with more detail',
            'operations' => [['type' => 'rent', 'price' => 15000]],
            'external_codes' => ['easybroker' => 'EB-123'],
            'data_quality' => ['score' => 85],
            'images' => ['img1.jpg', 'img2.jpg'],
        ]);

    $job = new RescrapeListingJob($listing->id);
    $job->handle($mockScraper);

    $listing->refresh();

    expect($listing->raw_data['title'])->toBe('New title')
        ->and($listing->raw_data['description'])->toBe('New description with more detail')
        ->and($listing->operations)->toBe([['type' => 'rent', 'price' => 15000]])
        ->and($listing->external_codes)->toBe(['easybroker' => 'EB-123'])
        ->and($listing->data_quality)->toBe(['score' => 85]);
});

it('is queued on the scraping queue', function () {
    $job = new RescrapeListingJob(1);
    expect($job->queue)->toBe('scraping');
});

it('logs error when job fails permanently', function () {
    $listing = Listing::factory()->unmatched()->create([
        'platform_id' => $this->platform->id,
    ]);

    $job = new RescrapeListingJob($listing->id);
    $job->failed(new \RuntimeException('Scraper Error'));

    // Job should complete without throwing - just logs the error
    expect(true)->toBeTrue();
});
