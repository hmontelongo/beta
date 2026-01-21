<?php

use App\Services\Scrapers\LamudiConfig;
use App\Services\Scrapers\LamudiSearchParser;

beforeEach(function () {
    $this->config = new LamudiConfig;
    $this->parser = new LamudiSearchParser($this->config);
});

it('parses search results with multiple listings', function () {
    $extracted = [
        'urls' => [
            'https://www.lamudi.com.mx/detalle/41032-73-6153fc4615a4-92a2-19baec7-ab01-7257',
            'https://www.lamudi.com.mx/detalle/41032-73-6153fc4615a4-92a2-19baec7-ab01-7257', // Duplicate
            'https://www.lamudi.com.mx/detalle/41032-73-6153fc4615a4-92a2-19baec7-ab01-7257',
            'https://www.lamudi.com.mx/detalle/41032-73-6153fc4615a4-92a2-19baec7-ab01-7257',
            'https://www.lamudi.com.mx/detalle/52143-84-7264gd5726b5-a3b3-20cbfd8-bc12-8368',
            'https://www.lamudi.com.mx/detalle/52143-84-7264gd5726b5-a3b3-20cbfd8-bc12-8368',
            'https://www.lamudi.com.mx/detalle/52143-84-7264gd5726b5-a3b3-20cbfd8-bc12-8368',
            'https://www.lamudi.com.mx/detalle/52143-84-7264gd5726b5-a3b3-20cbfd8-bc12-8368',
        ],
        'titles' => [
            'Casa en Renta en Ciudad Granja, Zapopan',
            'Departamento en Renta en Providencia, Guadalajara',
        ],
        'prices' => [
            '$ 155,000 MXN /mes',
            '$ 25,000 MXN /mes',
        ],
        'locations' => [
            'Ciudad Granja, Zapopan, Jalisco',
            'Providencia, Guadalajara, Jalisco',
        ],
        'images' => [
            'https://images.lamudi.com.mx/image1.jpg',
            'https://images.lamudi.com.mx/image2.jpg',
        ],
        'h1_title' => '38 Inmuebles en Renta en Ciudad Granja, Zapopan',
        'pagination_text' => ['Página 1 de 40'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx/jalisco/zapopan/ciudad-granja/for-rent/');

    expect($result)
        ->toHaveKey('total_results')
        ->toHaveKey('visible_pages')
        ->toHaveKey('listings')
        ->and($result['total_results'])->toBe(38)
        ->and($result['visible_pages'])->toBe(range(1, 40))
        ->and($result['listings'])->toHaveCount(2); // Deduplicated

    // Check first listing
    $first = $result['listings'][0];
    expect($first['url'])->toBe('https://www.lamudi.com.mx/detalle/41032-73-6153fc4615a4-92a2-19baec7-ab01-7257')
        ->and($first['external_id'])->toBe('41032-73-6153fc4615a4-92a2-19baec7-ab01-7257')
        ->and($first['preview']['title'])->toBe('Casa en Renta en Ciudad Granja, Zapopan')
        ->and($first['preview']['price'])->toBe('$ 155,000 MXN /mes')
        ->and($first['preview']['location'])->toBe('Ciudad Granja, Zapopan, Jalisco');
});

it('removes URL hash fragments', function () {
    $extracted = [
        'urls' => [
            'https://www.lamudi.com.mx/detalle/41032-73-test-id#photos',
        ],
        'titles' => ['Casa en Renta en Test Location'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx');

    expect($result['listings'][0]['url'])
        ->toBe('https://www.lamudi.com.mx/detalle/41032-73-test-id');
});

it('extracts external ID from URL', function () {
    $extracted = [
        'urls' => [
            'https://www.lamudi.com.mx/detalle/41032-73-6153fc4615a4-92a2-19baec7-ab01-7257',
        ],
        'titles' => ['Casa en Renta in Test'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx');

    expect($result['listings'][0]['external_id'])->toBe('41032-73-6153fc4615a4-92a2-19baec7-ab01-7257');
});

it('filters out navigation titles and keeps only listing titles', function () {
    $extracted = [
        'urls' => ['/detalle/test-id-1'],
        'titles' => [
            'RECÁMARAS',           // Filter title - should be skipped
            'BAÑOS',               // Filter title - should be skipped
            'Superficies',         // Filter title - should be skipped
            'Casa en Renta en Providencia',  // Valid listing title
        ],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx');

    expect($result['listings'][0]['preview']['title'])
        ->toBe('Casa en Renta en Providencia');
});

it('deduplicates listings by URL', function () {
    $extracted = [
        'urls' => [
            'https://www.lamudi.com.mx/detalle/id-1',
            'https://www.lamudi.com.mx/detalle/id-1#section', // Same listing, different hash
            'https://www.lamudi.com.mx/detalle/id-2',
        ],
        'titles' => [
            'Casa en Venta in Test 1',
            'Casa en Venta in Test 2',
        ],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx');

    expect($result['listings'])->toHaveCount(2);
});

it('parses total results from H1 title', function () {
    $testCases = [
        '38 Inmuebles en Renta en Ciudad Granja, Zapopan' => 38,
        '1,234 Propiedades en venta' => 1234,
        '500 Casas en renta' => 500,
        'Inmuebles en venta' => null, // No number
    ];

    foreach ($testCases as $title => $expected) {
        $result = $this->parser->parse([
            'urls' => [],
            'h1_title' => $title,
        ], 'https://www.lamudi.com.mx');

        expect($result['total_results'])->toBe($expected, "Failed for: {$title}");
    }
});

it('extracts visible pages from pagination text', function () {
    $extracted = [
        'urls' => [],
        'h1_title' => '100 Casas',
        'pagination_text' => ['Página 1 de 40'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx');

    expect($result['visible_pages'])->toBe(range(1, 40));
});

it('caps visible pages at 100', function () {
    $extracted = [
        'urls' => [],
        'h1_title' => '5000 Casas',
        'pagination_text' => ['Página 1 de 200'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx');

    expect($result['visible_pages'])->toBe(range(1, 100));
});

it('handles empty extraction gracefully', function () {
    $extracted = [
        'urls' => [],
        'h1_title' => '',
        'pagination_text' => [],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx');

    expect($result['total_results'])->toBeNull()
        ->and($result['visible_pages'])->toBe([])
        ->and($result['listings'])->toBeEmpty();
});

it('skips non-listing URLs', function () {
    $extracted = [
        'urls' => [
            'https://www.lamudi.com.mx/jalisco/zapopan/for-rent/', // Search URL, not listing
            'https://www.lamudi.com.mx/login', // Login page
            'https://www.lamudi.com.mx/detalle/valid-listing-id', // Valid listing
        ],
        'titles' => ['Casa en Renta in Valid'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx');

    expect($result['listings'])->toHaveCount(1)
        ->and($result['listings'][0]['url'])->toContain('/detalle/');
});

it('handles single values as arrays from ZenRows', function () {
    $extracted = [
        'urls' => '/detalle/single-listing-id',
        'titles' => 'Casa en Venta in Single Title',
        'prices' => '$ 15,000 MXN /mes',
        'h1_title' => '1 Casa en Renta',
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx');

    expect($result['listings'])->toHaveCount(1)
        ->and($result['listings'][0]['preview']['title'])->toBe('Casa en Venta in Single Title');
});

it('resolves relative URLs', function () {
    $extracted = [
        'urls' => ['/detalle/relative-id'],
        'titles' => ['Casa en Renta in Relative'],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx/jalisco/for-rent/');

    expect($result['listings'][0]['url'])
        ->toBe('https://www.lamudi.com.mx/detalle/relative-id');
});

it('cleans price text', function () {
    $extracted = [
        'urls' => ['/detalle/test-id'],
        'titles' => ['Casa en Venta in Test'],
        'prices' => ['   $ 155,000   MXN  /mes   '],
    ];

    $result = $this->parser->parse($extracted, 'https://www.lamudi.com.mx');

    expect($result['listings'][0]['preview']['price'])
        ->toBe('$ 155,000 MXN /mes');
});
