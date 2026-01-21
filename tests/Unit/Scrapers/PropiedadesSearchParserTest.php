<?php

use App\Services\Scrapers\PropiedadesConfig;
use App\Services\Scrapers\PropiedadesSearchParser;

beforeEach(function () {
    $this->config = new PropiedadesConfig;
    $this->parser = new PropiedadesSearchParser($this->config);
});

it('parses search results with multiple listings', function () {
    $extracted = [
        'urls' => [
            'https://propiedades.com/inmuebles/casa-en-renta-senderos-de-monte-verde-jalisco-30554556#tipos=casas-renta',
            'https://propiedades.com/inmuebles/casa-en-renta-labaro-patrio-454-454-jacarandas-jalisco-30017736',
        ],
        'titles' => [
            'Renta de casa en Senderos de Monte Verde, ID: 30554556',
            'Se renta casa Lábaro Patrio 454, ID: 30017736',
        ],
        'prices' => [
            '$13,500 MXN',
            '$65,000 MXN',
        ],
        'locations' => [
            'Tlajomulco de Zúñiga',
            'Zapopan',
        ],
        'images' => [
            'https://cdn.propiedades.com/files/600x400/eb95a609-d30c-11f0-9755-2bd181ff1149.jpeg',
            'https://cdn.propiedades.com/files/600x400/b0b7e8cd-d535-11f0-b953-2d344f241ddd.jpeg',
        ],
        'h1_title' => '2,163 Casas en Renta en Jalisco',
        'pagination_links' => [
            'https://propiedades.com/jalisco/casas-renta?pagina=2',
            'https://propiedades.com/jalisco/casas-renta?pagina=47',
        ],
    ];

    $result = $this->parser->parse($extracted, 'https://propiedades.com/jalisco/casas-renta');

    expect($result)
        ->toHaveKey('total_results')
        ->toHaveKey('visible_pages')
        ->toHaveKey('listings')
        ->and($result['total_results'])->toBe(2163)
        ->and($result['visible_pages'])->toBe([2, 47]) // Visible pages from pagination
        ->and($result['listings'])->toHaveCount(2);

    // Check first listing
    $first = $result['listings'][0];
    expect($first['url'])->toBe('https://propiedades.com/inmuebles/casa-en-renta-senderos-de-monte-verde-jalisco-30554556')
        ->and($first['external_id'])->toBe('30554556')
        ->and($first['preview']['title'])->toBe('Renta de casa en Senderos de Monte Verde')
        ->and($first['preview']['price'])->toBe('$13,500 MXN')
        ->and($first['preview']['location'])->toBe('Tlajomulco de Zúñiga');
});

it('removes URL hash fragments and query params', function () {
    $extracted = [
        'urls' => [
            'https://propiedades.com/inmuebles/casa-en-renta-example-30554556#tipos=casas-renta&area=jalisco&pos=1',
        ],
        'titles' => ['Test'],
    ];

    $result = $this->parser->parse($extracted, 'https://propiedades.com');

    expect($result['listings'][0]['url'])
        ->toBe('https://propiedades.com/inmuebles/casa-en-renta-example-30554556');
});

it('extracts external ID from URL', function () {
    $extracted = [
        'urls' => [
            'https://propiedades.com/inmuebles/casa-en-renta-senderos-jalisco-30554556',
        ],
        'titles' => ['Test'],
    ];

    $result = $this->parser->parse($extracted, 'https://propiedades.com');

    expect($result['listings'][0]['external_id'])->toBe('30554556');
});

it('removes ID suffix from titles', function () {
    $extracted = [
        'urls' => ['/inmuebles/test-30554556'],
        'titles' => ['Renta de casa en Providencia, ID: 30554556'],
    ];

    $result = $this->parser->parse($extracted, 'https://propiedades.com');

    expect($result['listings'][0]['preview']['title'])
        ->toBe('Renta de casa en Providencia');
});

it('deduplicates listings by URL', function () {
    $extracted = [
        'urls' => [
            'https://propiedades.com/inmuebles/casa-30554556',
            'https://propiedades.com/inmuebles/casa-30554556#pos=2', // Same, different hash
            'https://propiedades.com/inmuebles/casa-30017736',
        ],
        'titles' => ['Title 1', 'Title 2', 'Title 3'],
    ];

    $result = $this->parser->parse($extracted, 'https://propiedades.com');

    expect($result['listings'])->toHaveCount(2);
});

it('parses total results from H1 title', function () {
    $testCases = [
        '2,163 Casas en Renta en Jalisco' => 2163,
        '1.234 Departamentos en venta' => 1234,
        '500 Propiedades en renta' => 500,
        'Casas en venta' => null, // No number
    ];

    foreach ($testCases as $title => $expected) {
        $result = $this->parser->parse([
            'urls' => [],
            'h1_title' => $title,
        ], 'https://propiedades.com');

        expect($result['total_results'])->toBe($expected, "Failed for: {$title}");
    }
});

it('extracts visible pages from pagination links', function () {
    $extracted = [
        'urls' => [],
        'h1_title' => '100 Casas',
        'pagination_links' => [
            'https://propiedades.com/jalisco/casas-renta?pagina=2',
            'https://propiedades.com/jalisco/casas-renta?pagina=3',
            'https://propiedades.com/jalisco/casas-renta?pagina=10',
        ],
    ];

    $result = $this->parser->parse($extracted, 'https://propiedades.com');

    expect($result['visible_pages'])->toBe([2, 3, 10]);
});

it('handles empty extraction gracefully', function () {
    $extracted = [
        'urls' => [],
        'h1_title' => '',
        'pagination_links' => [],
    ];

    $result = $this->parser->parse($extracted, 'https://propiedades.com');

    expect($result['total_results'])->toBeNull()
        ->and($result['visible_pages'])->toBe([])
        ->and($result['listings'])->toBeEmpty();
});

it('skips non-listing URLs', function () {
    $extracted = [
        'urls' => [
            'https://propiedades.com/jalisco/casas-renta', // Search URL, not listing
            'https://propiedades.com/login', // Login page
            'https://propiedades.com/inmuebles/valid-listing-30554556', // Valid listing
        ],
        'titles' => ['Title 1', 'Title 2', 'Title 3'],
    ];

    $result = $this->parser->parse($extracted, 'https://propiedades.com');

    expect($result['listings'])->toHaveCount(1)
        ->and($result['listings'][0]['url'])->toContain('/inmuebles/');
});

it('handles single values as arrays from ZenRows', function () {
    $extracted = [
        'urls' => '/inmuebles/casa-30554556', // Single string, not array
        'titles' => 'Single Title',
        'prices' => '$15,000 MXN',
        'h1_title' => '1 Casa en Renta',
    ];

    $result = $this->parser->parse($extracted, 'https://propiedades.com');

    expect($result['listings'])->toHaveCount(1)
        ->and($result['listings'][0]['preview']['title'])->toBe('Single Title');
});

it('extracts visible pages from pagination numbers', function () {
    $extracted = [
        'urls' => [],
        'h1_title' => '2,400 Casas',
        'pagination_links' => [],
        'pagination_numbers' => ['1', '2', '3', '4', '5'], // Visible numbers in pagination UI
    ];

    $result = $this->parser->parse($extracted, 'https://propiedades.com');

    expect($result['visible_pages'])->toBe([1, 2, 3, 4, 5]);
});
