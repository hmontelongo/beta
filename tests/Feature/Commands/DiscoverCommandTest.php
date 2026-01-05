<?php

use App\Jobs\DiscoverSearchJob;
use App\Models\Platform;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('dispatches discovery job for valid platform', function () {
    $platform = Platform::factory()->create(['name' => 'inmuebles24']);

    $this->artisan('scrape:discover', [
        'platform' => 'inmuebles24',
        'search_url' => 'https://www.inmuebles24.com/departamentos-en-renta.html',
    ])
        ->assertSuccessful()
        ->expectsOutput('Discovery started for inmuebles24, job dispatched.');

    Queue::assertPushed(DiscoverSearchJob::class, function ($job) use ($platform) {
        return $job->platformId === $platform->id
            && $job->searchUrl === 'https://www.inmuebles24.com/departamentos-en-renta.html';
    });
});

it('fails for unknown platform', function () {
    $this->artisan('scrape:discover', [
        'platform' => 'unknown-platform',
        'search_url' => 'https://example.com/search',
    ])
        ->assertFailed()
        ->expectsOutput("Platform 'unknown-platform' not found.");

    Queue::assertNotPushed(DiscoverSearchJob::class);
});

it('requires platform argument', function () {
    $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);

    $this->artisan('scrape:discover', [
        'search_url' => 'https://example.com/search',
    ]);
});

it('requires search_url argument', function () {
    Platform::factory()->create(['name' => 'inmuebles24']);

    $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);

    $this->artisan('scrape:discover', [
        'platform' => 'inmuebles24',
    ]);
});
