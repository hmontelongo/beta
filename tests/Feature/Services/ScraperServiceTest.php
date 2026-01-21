<?php

use App\Models\Platform;
use App\Services\ScraperService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.zenrows.api_key' => 'test-api-key']);
    config(['services.zenrows.timeout' => 30]);

    // Seed the inmuebles24 platform for URL detection
    Platform::factory()->create([
        'slug' => 'inmuebles24',
        'name' => 'Inmuebles24',
        'base_url' => 'https://www.inmuebles24.com',
    ]);
});

it('can discover listings from a search page', function () {
    Http::fake([
        'api.zenrows.com/*' => Http::response([
            'urls' => [
                '/propiedades/departamento-en-renta-123456.html',
                '/propiedades/departamento-en-renta-789012.html',
            ],
            'titles' => ['Test Listing 1', 'Test Listing 2'],
            'prices' => ['$25,000 MXN', '$30,000 MXN'],
            'locations' => ['Guadalajara, Jalisco', 'Zapopan, Jalisco'],
            'images' => [],
            'page_title' => '100 Departamentos en renta en Jalisco',
            'page_links' => ['PAGING_1', 'PAGING_2', 'PAGING_3', 'PAGING_4', 'PAGING_5'],
        ], 200),
    ]);

    $service = app(ScraperService::class);
    $result = $service->discoverPage('https://www.inmuebles24.com/departamentos-renta-jalisco.html', 1);

    expect($result)->toHaveKey('total_results')
        ->and($result)->toHaveKey('visible_pages')
        ->and($result)->toHaveKey('listings')
        ->and($result['total_results'])->toBe(100)
        ->and($result['visible_pages'])->toBe([1, 2, 3, 4, 5]) // Visible pages from pagination
        ->and($result['listings'])->toHaveCount(2);

    // Check first listing
    $first = $result['listings'][0];
    expect($first['url'])->toContain('departamento-en-renta-123456')
        ->and($first['external_id'])->toBe('123456')
        ->and($first['preview']['title'])->toBe('Test Listing 1');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.zenrows.com')
            && str_contains($request->url(), 'css_extractor');
    });
});

it('can scrape a single listing', function () {
    // Single request with CSS extraction + all_scripts for JS variables
    Http::fake([
        'api.zenrows.com/*' => Http::response([
            'title' => 'Hermoso Departamento',
            'description' => 'Amplio departamento con vista.',
            'bedrooms_text' => '3 recámaras',
            'bathrooms_text' => '2 baños',
            'location_header' => 'Providencia, Guadalajara, Jalisco',
            'gallery_images' => ['https://cdn.inmuebles24.com/img/360x266/photo.jpg'],
            // all_scripts contains the dataLayer script for JS variable extraction
            'all_scripts' => [
                "window.dataLayer = [{'price': '1500000', 'currencyId': '10', 'operationTypeId': '1', 'propertyTypeId': '2'}];",
            ],
        ], 200),
    ]);

    $service = app(ScraperService::class);
    $result = $service->scrapeListing('https://www.inmuebles24.com/propiedades/departamento-12345678.html');

    expect($result)->toHaveKey('title')
        ->and($result['title'])->toBe('Hermoso Departamento')
        ->and($result['bedrooms'])->toBe(3)
        ->and($result['operations'][0]['price'])->toBe(1500000)
        ->and($result['operations'][0]['currency'])->toBe('MXN');
});

it('throws exception on ZenRows failure for discover', function () {
    Http::fake([
        'api.zenrows.com/*' => Http::response('Error', 500),
    ]);

    $service = app(ScraperService::class);

    expect(fn () => $service->discoverPage('https://www.inmuebles24.com/search.html', 1))
        ->toThrow(\RuntimeException::class);
});

it('throws exception on ZenRows failure for scrape', function () {
    Http::fake([
        'api.zenrows.com/*' => Http::response('Error', 500),
    ]);

    $service = app(ScraperService::class);

    expect(fn () => $service->scrapeListing('https://www.inmuebles24.com/propiedades/test-123456.html'))
        ->toThrow(\RuntimeException::class);
});

it('handles empty discovery response gracefully', function () {
    Http::fake([
        'api.zenrows.com/*' => Http::response([
            'urls' => [],
            'page_title' => '',
            'page_links' => [],
        ], 200),
    ]);

    $service = app(ScraperService::class);
    $result = $service->discoverPage('https://www.inmuebles24.com/search.html', 1);

    expect($result['total_results'])->toBe(0)
        ->and($result['visible_pages'])->toBe([])
        ->and($result['listings'])->toBeArray()->toBeEmpty();
});

it('adds pagination suffix for page > 1', function () {
    Http::fake([
        'api.zenrows.com/*' => Http::response([
            'urls' => [],
            'page_title' => '',
            'page_links' => [],
        ], 200),
    ]);

    $service = app(ScraperService::class);
    $service->discoverPage('https://www.inmuebles24.com/departamentos-renta-jalisco.html', 5);

    Http::assertSent(function ($request) {
        // Check that the URL sent to ZenRows contains the paginated URL
        $requestedUrl = urldecode($request['url'] ?? '');

        return str_contains($requestedUrl, 'pagina-5');
    });
});

it('deduplicates listing URLs in discovery', function () {
    Http::fake([
        'api.zenrows.com/*' => Http::response([
            'urls' => [
                '/propiedades/departamento-123456.html',
                '/propiedades/departamento-123456.html', // Duplicate
                '/propiedades/departamento-789012.html',
            ],
            'titles' => ['Title 1', 'Title 2', 'Title 3'],
            'page_title' => '3 Departamentos',
            'page_links' => [],
        ], 200),
    ]);

    $service = app(ScraperService::class);
    $result = $service->discoverPage('https://www.inmuebles24.com/search.html', 1);

    expect($result['listings'])->toHaveCount(2);
});

it('extracts external ID from listing URL', function () {
    Http::fake([
        'api.zenrows.com/*' => Http::sequence()
            ->push(['title' => 'Test'], 200)
            ->push('', 200),
    ]);

    $service = app(ScraperService::class);
    $result = $service->scrapeListing('https://www.inmuebles24.com/propiedades/departamento-87654321.html');

    expect($result['external_id'])->toBe('87654321');
});
