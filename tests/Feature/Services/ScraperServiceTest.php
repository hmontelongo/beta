<?php

use App\Services\ScraperService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.scraper.url' => 'http://localhost:3000']);
    config(['services.scraper.timeout' => 30]);
});

it('can discover listings from a search page', function () {
    Http::fake([
        'localhost:3000/discover*' => Http::response([
            'total_results' => 100,
            'total_pages' => 5,
            'listings' => [
                ['url' => 'https://example.com/listing/1', 'external_id' => 'abc123'],
                ['url' => 'https://example.com/listing/2', 'external_id' => 'def456'],
            ],
        ], 200),
    ]);

    $service = new ScraperService;
    $result = $service->discoverPage('https://example.com/search', 1);

    expect($result)->toHaveKey('total_results')
        ->and($result)->toHaveKey('total_pages')
        ->and($result)->toHaveKey('listings')
        ->and($result['total_results'])->toBe(100)
        ->and($result['total_pages'])->toBe(5)
        ->and($result['listings'])->toHaveCount(2);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:3000/discover?url=https%3A%2F%2Fexample.com%2Fsearch&page=1';
    });
});

it('can scrape a single listing', function () {
    Http::fake([
        'localhost:3000/scrape*' => Http::response([
            'title' => 'Test Listing',
            'price' => 1500000,
            'bedrooms' => 3,
        ], 200),
    ]);

    $service = new ScraperService;
    $result = $service->scrapeListing('https://example.com/listing/1');

    expect($result)->toHaveKey('title')
        ->and($result['title'])->toBe('Test Listing')
        ->and($result['price'])->toBe(1500000);
});

it('throws exception on connection failure for discover', function () {
    Http::fake([
        'localhost:3000/discover*' => Http::response(null, 500),
    ]);

    $service = new ScraperService;

    expect(fn () => $service->discoverPage('https://example.com/search', 1))
        ->toThrow(\RuntimeException::class);
});

it('throws exception on connection failure for scrape', function () {
    Http::fake([
        'localhost:3000/scrape*' => Http::response(null, 500),
    ]);

    $service = new ScraperService;

    expect(fn () => $service->scrapeListing('https://example.com/listing/1'))
        ->toThrow(\RuntimeException::class);
});

it('handles missing fields in discover response gracefully', function () {
    Http::fake([
        'localhost:3000/discover*' => Http::response([
            'listings' => [],
        ], 200),
    ]);

    $service = new ScraperService;
    $result = $service->discoverPage('https://example.com/search', 1);

    expect($result['total_results'])->toBe(0)
        ->and($result['total_pages'])->toBe(1)
        ->and($result['listings'])->toBeArray()->toBeEmpty();
});

it('uses correct base url from config', function () {
    config(['services.scraper.url' => 'http://scraper.local:8080/']);

    Http::fake([
        'scraper.local:8080/discover*' => Http::response([
            'total_results' => 0,
            'total_pages' => 1,
            'listings' => [],
        ], 200),
    ]);

    $service = new ScraperService;
    $service->discoverPage('https://example.com/search', 1);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'scraper.local:8080');
    });
});

it('passes page number correctly', function () {
    Http::fake([
        'localhost:3000/discover*' => Http::response([
            'total_results' => 0,
            'total_pages' => 1,
            'listings' => [],
        ], 200),
    ]);

    $service = new ScraperService;
    $service->discoverPage('https://example.com/search', 5);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'page=5');
    });
});
