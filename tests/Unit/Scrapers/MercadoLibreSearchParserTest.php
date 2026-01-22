<?php

use App\Services\Scrapers\MercadoLibreConfig;
use App\Services\Scrapers\MercadoLibreSearchParser;

beforeEach(function () {
    $this->config = new MercadoLibreConfig;
    $this->parser = new MercadoLibreSearchParser($this->config);
});

it('parses search results with multiple listings', function () {
    $extracted = [
        'urls' => [
            'https://casa.mercadolibre.com.mx/MLM-2698010529-casa-en-renta-valle-real-zapopan_JM#tracking_id=123',
            'https://casa.mercadolibre.com.mx/MLM-2698010529-casa-en-renta-valle-real-zapopan_JM#tracking_id=456', // Duplicate
            'https://casa.mercadolibre.com.mx/MLM-2700123456-departamento-en-renta_JM#position=2',
        ],
        'titles' => [
            'Casa en Renta Valle Real',
            'Departamento en Renta Providencia',
        ],
        'prices' => [
            '$ 65,000',
            '$ 25,000/mes',
        ],
        'locations' => [
            'Zapopan, Jalisco',
            'Guadalajara, Jalisco',
        ],
        'images' => [
            'https://http2.mlstatic.com/image1.jpg',
            'https://http2.mlstatic.com/image2.jpg',
        ],
        'h1_title' => '49 Casas en Renta en Zapopan, Jalisco',
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx/casas/renta/jalisco/zapopan/');

    expect($result)
        ->toHaveKey('total_results')
        ->toHaveKey('visible_pages')
        ->toHaveKey('listings')
        ->and($result['total_results'])->toBe(49)
        ->and($result['listings'])->toHaveCount(2); // Deduplicated

    // Check first listing
    $first = $result['listings'][0];
    expect($first['url'])->toBe('https://casa.mercadolibre.com.mx/MLM-2698010529-casa-en-renta-valle-real-zapopan_JM')
        ->and($first['external_id'])->toBe('MLM2698010529')
        ->and($first['preview']['title'])->toBe('Casa en Renta Valle Real')
        ->and($first['preview']['price'])->toBe('$ 65,000');
});

it('removes URL tracking fragments and query parameters', function () {
    $extracted = [
        'urls' => [
            'https://casa.mercadolibre.com.mx/MLM-123456789-test-listing_JM#tracking_id=abc&position=1',
        ],
        'titles' => ['Casa en Test'],
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx');

    expect($result['listings'][0]['url'])
        ->toBe('https://casa.mercadolibre.com.mx/MLM-123456789-test-listing_JM');
});

it('extracts external ID in MLM format', function () {
    $extracted = [
        'urls' => [
            'https://casa.mercadolibre.com.mx/MLM-2698010529-casa-descripcion_JM',
        ],
        'titles' => ['Casa Test'],
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx');

    expect($result['listings'][0]['external_id'])->toBe('MLM2698010529');
});

it('extracts external ID with hyphen format', function () {
    $extracted = [
        'urls' => [
            'https://casa.mercadolibre.com.mx/MLM2698010529-casa-descripcion_JM',
        ],
        'titles' => ['Casa Test'],
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx');

    expect($result['listings'][0]['external_id'])->toBe('MLM2698010529');
});

it('deduplicates listings by URL', function () {
    $extracted = [
        'urls' => [
            'https://casa.mercadolibre.com.mx/MLM-1234-casa_JM',
            'https://casa.mercadolibre.com.mx/MLM-1234-casa_JM#tracking', // Same listing
            'https://casa.mercadolibre.com.mx/MLM-5678-otra-casa_JM',
        ],
        'titles' => [
            'Casa 1',
            'Casa 2',
        ],
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx');

    expect($result['listings'])->toHaveCount(2);
});

it('parses total results from H1 title', function () {
    $testCases = [
        '49 Casas en Renta en Zapopan, Jalisco' => 49,
        '1,234 Departamentos en venta' => 1234,
        '500 Inmuebles en renta' => 500,
        'Casas en venta' => null, // No number
    ];

    foreach ($testCases as $title => $expected) {
        $result = $this->parser->parse([
            'urls' => [],
            'h1_title' => $title,
        ], 'https://inmuebles.mercadolibre.com.mx');

        expect($result['total_results'])->toBe($expected, "Failed for: {$title}");
    }
});

it('calculates visible pages from total results', function () {
    $extracted = [
        'urls' => [],
        'h1_title' => '96 Casas en Renta', // 96 items / 48 per page = 2 pages
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx');

    expect($result['visible_pages'])->toBe([1, 2]);
});

it('caps visible pages at 100', function () {
    $extracted = [
        'urls' => [],
        'h1_title' => '10000 Casas en Renta', // 10000 / 48 = 208 pages, cap at 100
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx');

    expect($result['visible_pages'])->toBe(range(1, 100));
});

it('handles empty extraction gracefully', function () {
    $extracted = [
        'urls' => [],
        'h1_title' => '',
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx');

    expect($result['total_results'])->toBeNull()
        ->and($result['visible_pages'])->toBe([])
        ->and($result['listings'])->toBeEmpty();
});

it('skips non-listing URLs without MLM pattern', function () {
    $extracted = [
        'urls' => [
            'https://inmuebles.mercadolibre.com.mx/casas/renta/', // Search URL
            'https://www.mercadolibre.com.mx/login', // Login page
            'https://casa.mercadolibre.com.mx/MLM-123456-valid_JM', // Valid listing
        ],
        'titles' => ['Casa Valid'],
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx');

    expect($result['listings'])->toHaveCount(1)
        ->and($result['listings'][0]['external_id'])->toBe('MLM123456');
});

it('handles single values as arrays from ZenRows', function () {
    $extracted = [
        'urls' => 'https://casa.mercadolibre.com.mx/MLM-111111-single_JM',
        'titles' => 'Casa Single Title',
        'prices' => '$ 50,000',
        'h1_title' => '1 Casa en Renta',
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx');

    expect($result['listings'])->toHaveCount(1)
        ->and($result['listings'][0]['preview']['title'])->toBe('Casa Single Title');
});

it('cleans price text', function () {
    $extracted = [
        'urls' => ['https://casa.mercadolibre.com.mx/MLM-999-test_JM'],
        'titles' => ['Casa Test'],
        'prices' => ['   $ 65,000   /mes   '],
    ];

    $result = $this->parser->parse($extracted, 'https://inmuebles.mercadolibre.com.mx');

    expect($result['listings'][0]['preview']['price'])
        ->toBe('$ 65,000 /mes');
});
