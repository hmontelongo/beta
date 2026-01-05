<?php

use App\Enums\ScrapeJobStatus;
use App\Models\Platform;
use App\Models\ScrapeJob;

it('can create a scrape job', function () {
    $scrapeJob = ScrapeJob::factory()->create();

    expect($scrapeJob)->toBeInstanceOf(ScrapeJob::class)
        ->and($scrapeJob->id)->not->toBeNull()
        ->and($scrapeJob->target_url)->not->toBeEmpty();
});

it('belongs to a platform', function () {
    $platform = Platform::factory()->create();
    $scrapeJob = ScrapeJob::factory()->for($platform)->create();

    expect($scrapeJob->platform)->toBeInstanceOf(Platform::class)
        ->and($scrapeJob->platform->id)->toBe($platform->id);
});

it('casts status to enum', function () {
    $scrapeJob = ScrapeJob::factory()->create(['status' => ScrapeJobStatus::Running]);

    $scrapeJob->refresh();

    expect($scrapeJob->status)->toBeInstanceOf(ScrapeJobStatus::class)
        ->and($scrapeJob->status)->toBe(ScrapeJobStatus::Running);
});

it('casts filters to array', function () {
    $filters = [
        'city' => 'guadalajara',
        'type' => 'apartment',
        'operation' => 'rent',
    ];

    $scrapeJob = ScrapeJob::factory()->create(['filters' => $filters]);

    $scrapeJob->refresh();

    expect($scrapeJob->filters)->toBeArray()
        ->and($scrapeJob->filters['city'])->toBe('guadalajara')
        ->and($scrapeJob->filters['type'])->toBe('apartment');
});

it('casts started_at to datetime', function () {
    $scrapeJob = ScrapeJob::factory()->running()->create();

    expect($scrapeJob->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('casts completed_at to datetime', function () {
    $scrapeJob = ScrapeJob::factory()->completed()->create();

    expect($scrapeJob->completed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('defaults to Pending status', function () {
    $scrapeJob = ScrapeJob::factory()->create();

    expect($scrapeJob->status)->toBe(ScrapeJobStatus::Pending);
});

it('can be created as running', function () {
    $scrapeJob = ScrapeJob::factory()->running()->create();

    expect($scrapeJob->status)->toBe(ScrapeJobStatus::Running)
        ->and($scrapeJob->started_at)->not->toBeNull();
});

it('can be created as completed', function () {
    $scrapeJob = ScrapeJob::factory()->completed()->create();

    expect($scrapeJob->status)->toBe(ScrapeJobStatus::Completed)
        ->and($scrapeJob->started_at)->not->toBeNull()
        ->and($scrapeJob->completed_at)->not->toBeNull()
        ->and($scrapeJob->properties_found)->toBeGreaterThan(0);
});

it('can be created as failed', function () {
    $scrapeJob = ScrapeJob::factory()->failed()->create();

    expect($scrapeJob->status)->toBe(ScrapeJobStatus::Failed)
        ->and($scrapeJob->error_message)->not->toBeNull();
});

it('can be created with filters', function () {
    $scrapeJob = ScrapeJob::factory()->withFilters()->create();

    expect($scrapeJob->filters)->toBeArray()
        ->and($scrapeJob->filters)->toHaveKey('city')
        ->and($scrapeJob->filters)->toHaveKey('type')
        ->and($scrapeJob->filters)->toHaveKey('operation');
});

it('tracks property counts when completed', function () {
    $scrapeJob = ScrapeJob::factory()->completed()->create();

    expect($scrapeJob->properties_found)->toBeGreaterThanOrEqual($scrapeJob->properties_new)
        ->and($scrapeJob->properties_found)->toBe($scrapeJob->properties_new + $scrapeJob->properties_updated);
});

it('allows nullable error_message', function () {
    $scrapeJob = ScrapeJob::factory()->create(['error_message' => null]);

    expect($scrapeJob->error_message)->toBeNull();
});
