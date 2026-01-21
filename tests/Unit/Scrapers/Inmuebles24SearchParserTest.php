<?php

use App\Services\Scrapers\Inmuebles24Config;
use App\Services\Scrapers\Inmuebles24SearchParser;

beforeEach(function () {
    $this->config = new Inmuebles24Config;
    $this->parser = new Inmuebles24SearchParser($this->config);
});

it('parses search results with multiple listings', function () {
    $extracted = [
        'urls' => [
            '/propiedades/departamento-en-renta-guadalajara-123456.html',
            '/propiedades/departamento-en-renta-zapopan-789012.html',
        ],
        'titles' => [
            'Hermoso departamento en Providencia',
            'Departamento moderno en Andares',
        ],
        'prices' => [
            '$25,000 MXN',
            '$35,000 MXN',
        ],
        'locations' => [
            'Providencia, Guadalajara',
            'Andares, Zapopan',
        ],
        'images' => [
            'https://cdn.inmuebles24.com/img/360x266/12345.jpg',
            'https://cdn.inmuebles24.com/img/360x266/67890.jpg',
        ],
        'page_title' => '954 Departamentos en renta en Jalisco',
        'page_links' => ['PAGING_1', 'PAGING_2', 'PAGING_3'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.inmuebles24.com');

    expect($result)
        ->toHaveKey('total_results')
        ->toHaveKey('visible_pages')
        ->toHaveKey('listings')
        ->and($result['total_results'])->toBe(954)
        ->and($result['visible_pages'])->toBe([1, 2, 3]) // Visible pages from pagination
        ->and($result['listings'])->toHaveCount(2);

    // Check first listing
    $first = $result['listings'][0];
    expect($first['url'])->toBe('https://www.inmuebles24.com/propiedades/departamento-en-renta-guadalajara-123456.html')
        ->and($first['external_id'])->toBe('123456')
        ->and($first['preview']['title'])->toBe('Hermoso departamento en Providencia')
        ->and($first['preview']['price'])->toBe('$25,000 MXN')
        ->and($first['preview']['location'])->toBe('Providencia, Guadalajara')
        ->and($first['preview']['image'])->toContain('1200x1200'); // Upgraded resolution
});

it('deduplicates listings by URL', function () {
    $extracted = [
        'urls' => [
            '/propiedades/departamento-123456.html',
            '/propiedades/departamento-123456.html', // Duplicate
            '/propiedades/departamento-789012.html',
        ],
        'titles' => ['Title 1', 'Title 2', 'Title 3'],
        'prices' => ['$10,000', '$10,000', '$20,000'],
        'locations' => ['Location 1', 'Location 2', 'Location 3'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.inmuebles24.com');

    expect($result['listings'])->toHaveCount(2);
});

it('extracts external ID from URL', function () {
    $extracted = [
        'urls' => [
            '/propiedades/departamento-en-renta-guadalajara-12345678.html',
        ],
        'titles' => ['Test'],
        'prices' => ['$10,000'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.inmuebles24.com');

    expect($result['listings'][0]['external_id'])->toBe('12345678');
});

it('handles empty extraction gracefully', function () {
    $extracted = [
        'urls' => [],
        'page_title' => '',
        'page_links' => [],
    ];

    $result = $this->parser->parse($extracted, 'https://www.inmuebles24.com');

    expect($result['total_results'])->toBe(0)
        ->and($result['visible_pages'])->toBe([])
        ->and($result['listings'])->toBeEmpty();
});

it('parses total results from page title', function () {
    $testCases = [
        '954 Departamentos en renta en Jalisco' => 954,
        '1,234 Casas en venta en México' => 1234,
        '5.678 Inmuebles en renta' => 5678,
        'Propiedades en venta' => 0, // No number
    ];

    foreach ($testCases as $title => $expected) {
        $result = $this->parser->parse([
            'urls' => [],
            'page_title' => $title,
            'page_links' => [],
        ], 'https://www.inmuebles24.com');

        expect($result['total_results'])->toBe($expected, "Failed for: {$title}");
    }
});

it('extracts visible pages from pagination links', function () {
    $extracted = [
        'urls' => [],
        'page_title' => '30 Departamentos',
        'page_links' => ['PAGING_1', 'PAGING_2', 'PAGING_5'], // Visible pages in pagination UI
    ];

    $result = $this->parser->parse($extracted, 'https://www.inmuebles24.com');

    expect($result['visible_pages'])->toBe([1, 2, 5]);
});

it('handles absolute URLs correctly', function () {
    $extracted = [
        'urls' => [
            'https://www.inmuebles24.com/propiedades/casa-123456.html?tracking=abc',
        ],
        'titles' => ['Test'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.inmuebles24.com');

    // Should strip query params
    expect($result['listings'][0]['url'])
        ->toBe('https://www.inmuebles24.com/propiedades/casa-123456.html');
});

it('handles single values as arrays from ZenRows', function () {
    // ZenRows may return a single value instead of array when there's only one match
    $extracted = [
        'urls' => '/propiedades/departamento-123456.html', // Single string, not array
        'titles' => 'Single Title',
        'prices' => '$15,000',
        'page_title' => '1 Departamento',
    ];

    $result = $this->parser->parse($extracted, 'https://www.inmuebles24.com');

    expect($result['listings'])->toHaveCount(1)
        ->and($result['listings'][0]['preview']['title'])->toBe('Single Title');
});

it('cleans whitespace from extracted text', function () {
    $extracted = [
        'urls' => ['/propiedades/dep-123456.html'],
        'titles' => ["  Departamento   con\n\textra  espacios  "],
        'prices' => ['  $10,000  '],
    ];

    $result = $this->parser->parse($extracted, 'https://www.inmuebles24.com');

    expect($result['listings'][0]['preview']['title'])
        ->toBe('Departamento con extra espacios');
});

it('skips empty URLs', function () {
    $extracted = [
        'urls' => ['', '/propiedades/valid-123456.html', null, ''],
        'titles' => ['Empty 1', 'Valid', 'Empty 2', 'Empty 3'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.inmuebles24.com');

    expect($result['listings'])->toHaveCount(1)
        ->and($result['listings'][0]['external_id'])->toBe('123456');
});
