<?php

use App\Services\Scrapers\MercadoLibreConfig;
use App\Services\Scrapers\MercadoLibreListingParser;

beforeEach(function () {
    $this->config = new MercadoLibreConfig;
    $this->parser = new MercadoLibreListingParser($this->config);
});

it('parses listing from JSON-LD structured data', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Product",
            "name": "Casa en Renta en Valle Real",
            "description": "Hermosa casa con 5 recámaras y amplio jardín",
            "sku": "MLM2698010529",
            "offers": {
                "@type": "Offer",
                "price": 65000,
                "priceCurrency": "MXN"
            }
        }
        </script>
        <script type="application/ld+json">
        {
            "@type": "BreadcrumbList",
            "itemListElement": [
                {"position": 1, "name": "Inmuebles"},
                {"position": 2, "name": "Casas"},
                {"position": 3, "name": "Renta"},
                {"position": 4, "name": "Jalisco"},
                {"position": 5, "name": "Zapopan"},
                {"position": 6, "name": "Valle Real"}
            ]
        }
        </script>
    </head>
    <body>
        <h1>Casa en Renta en Valle Real</h1>
        <script>
            var config = {
                latitude: 20.7279399,
                longitude: -103.4448482
            };
        </script>
    </body>
    </html>
    HTML;

    $extracted = [
        'title' => 'Casa en Renta en Valle Real',
        'description' => 'Hermosa casa con 5 recámaras y amplio jardín',
    ];

    $result = $this->parser->parse($extracted, $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-2698010529-casa-en-renta-valle-real_JM');

    expect($result)
        ->toHaveKey('external_id')
        ->toHaveKey('operations')
        ->toHaveKey('latitude')
        ->toHaveKey('longitude')
        ->and($result['external_id'])->toBe('MLM2698010529')
        ->and($result['title'])->toBe('Casa en Renta en Valle Real')
        ->and($result['operations'])->toHaveCount(1)
        ->and($result['operations'][0]['type'])->toBe('rent')
        ->and($result['operations'][0]['price'])->toBe(65000)
        ->and($result['operations'][0]['currency'])->toBe('MXN')
        ->and($result['latitude'])->toBe(20.7279399)
        ->and($result['longitude'])->toBe(-103.4448482)
        ->and($result['state'])->toBe('Jalisco')
        ->and($result['city'])->toBe('Zapopan')
        ->and($result['colonia'])->toBe('Valle Real');
});

it('extracts coordinates from JavaScript variables', function () {
    $rawHtml = <<<'HTML'
    <html>
    <script>
        var mapConfig = {
            "latitude": "20.674429",
            "longitude": "-103.453683"
        };
    </script>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['latitude'])->toBe(20.674429)
        ->and($result['longitude'])->toBe(-103.453683);
});

it('validates coordinates are within Mexico bounds', function () {
    $rawHtml = <<<'HTML'
    <html>
    <script>
        var config = {
            latitude: 40.7128,
            longitude: -74.0060
        };
    </script>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    // New York coordinates should be rejected
    expect($result['latitude'])->toBeNull()
        ->and($result['longitude'])->toBeNull();
});

it('extracts features from HTML patterns', function () {
    $rawHtml = <<<'HTML'
    <html>
    <body>
        <div>Recámaras5</div>
        <div>Baños4</div>
        <div>Medio baño1</div>
        <div>Lugares de estacionamiento6</div>
        <div>Superficie total450 m²</div>
        <div>Superficie construida400 m²</div>
        <div>Antigüedad21 años</div>
    </body>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['bedrooms'])->toBe(5)
        ->and($result['bathrooms'])->toBe(4.0)
        ->and($result['half_bathrooms'])->toBe(1)
        ->and($result['parking_spots'])->toBe(6)
        ->and($result['lot_size_m2'])->toBe(450.0)
        ->and($result['built_size_m2'])->toBe(400.0)
        ->and($result['age_years'])->toBe(21);
});

it('extracts external ID from URL', function () {
    $result = $this->parser->parse([], '<html></html>', 'https://casa.mercadolibre.com.mx/MLM-2698010529-casa-en-renta_JM');

    expect($result['external_id'])->toBe('MLM2698010529');
});

it('extracts external ID from JSON-LD SKU', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {"@type": "Product", "sku": "MLM9876543210"}
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-9876543210-test_JM');

    expect($result['external_id'])->toBe('MLM9876543210');
});

it('extracts property codes from HTML', function () {
    $rawHtml = '<html><body>Código: EB-VB8539</body></html>';

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['external_codes'])->toHaveKey('property_code')
        ->and($result['external_codes']['property_code'])->toBe('EB-VB8539');
});

it('resolves property type from URL subdomain', function () {
    $result = $this->parser->parse([], '<html></html>', 'https://departamento.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['property_type'])->toBe('apartment');
});

it('resolves property type from URL path', function () {
    $result = $this->parser->parse([], '<html></html>', 'https://inmuebles.mercadolibre.com.mx/departamentos/renta/MLM-123-test_JM');

    expect($result['property_type'])->toBe('apartment');
});

it('resolves property type from title as fallback', function () {
    $extracted = [
        'title' => 'Departamento en Renta en Providencia',
    ];

    $result = $this->parser->parse($extracted, '<html></html>', 'https://inmuebles.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['property_type'])->toBe('apartment');
});

it('detects property subtype from title', function () {
    $rawHtml = '<html></html>';
    $extracted = [
        'title' => 'Hermoso PENTHOUSE en renta',
    ];

    $result = $this->parser->parse($extracted, $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['property_subtype'])->toBe('penthouse');
});

it('standardizes amenities from feature tables', function () {
    $rawHtml = <<<'HTML'
    <html>
    <body>
        <table>
            <tr><th>Alberca</th><td>Sí</td></tr>
            <tr><th>Gimnasio</th><td>Sí</td></tr>
            <tr><th>Jardín</th><td>Sí</td></tr>
            <tr><th>Aire acondicionado</th><td>Sí</td></tr>
        </table>
    </body>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['amenities'])->toContain('pool')
        ->and($result['amenities'])->toContain('gym')
        ->and($result['amenities'])->toContain('garden')
        ->and($result['amenities'])->toContain('ac');
});

it('extracts location from BreadcrumbList', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"position": 1, "name": "Inmuebles"},
            {"position": 2, "name": "Casas"},
            {"position": 3, "name": "Renta"},
            {"position": 4, "name": "Jalisco"},
            {"position": 5, "name": "Guadalajara"},
            {"position": 6, "name": "Providencia"}
        ]
    }
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['state'])->toBe('Jalisco')
        ->and($result['city'])->toBe('Guadalajara')
        ->and($result['colonia'])->toBe('Providencia');
});

it('extracts description from meta tag as fallback', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <meta name="description" content="Casa en Renta de $65,000, 5 recámaras, 4 baños">
    </head>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['description'])->toContain('Casa en Renta');
});

it('determines operation type from URL for rent', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {
        "@type": "Product",
        "offers": {"price": 65000, "priceCurrency": "MXN"}
    }
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/casas/renta/MLM-123-test_JM');

    expect($result['operations'][0]['type'])->toBe('rent');
});

it('determines operation type from URL for sale', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {
        "@type": "Product",
        "offers": {"price": 2500000, "priceCurrency": "MXN"}
    }
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/casas/venta/MLM-123-test_JM');

    expect($result['operations'][0]['type'])->toBe('sale');
});

it('handles empty HTML gracefully', function () {
    $result = $this->parser->parse([], '', 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result)
        ->toHaveKey('external_id')
        ->toHaveKey('operations')
        ->toHaveKey('images')
        ->and($result['external_id'])->toBe('MLM123')
        ->and($result['operations'])->toBeEmpty()
        ->and($result['images'])->toBeEmpty();
});

it('returns correct data structure', function () {
    $result = $this->parser->parse([], '<html></html>', 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result)
        ->toHaveKey('external_id')
        ->toHaveKey('original_url')
        ->toHaveKey('title')
        ->toHaveKey('description')
        ->toHaveKey('operations')
        ->toHaveKey('bedrooms')
        ->toHaveKey('bathrooms')
        ->toHaveKey('half_bathrooms')
        ->toHaveKey('parking_spots')
        ->toHaveKey('lot_size_m2')
        ->toHaveKey('built_size_m2')
        ->toHaveKey('age_years')
        ->toHaveKey('property_type')
        ->toHaveKey('property_subtype')
        ->toHaveKey('address')
        ->toHaveKey('colonia')
        ->toHaveKey('city')
        ->toHaveKey('state')
        ->toHaveKey('postal_code')
        ->toHaveKey('latitude')
        ->toHaveKey('longitude')
        ->toHaveKey('images')
        ->toHaveKey('amenities')
        ->toHaveKey('external_codes')
        ->toHaveKey('data_quality')
        ->toHaveKey('platform_metadata');
});

it('extracts publisher name from CSS extraction', function () {
    $extracted = [
        'publisher_name' => 'Link Inmobiliario Gdl',
    ];

    $result = $this->parser->parse($extracted, '<html></html>', 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['publisher_name'])->toBe('Link Inmobiliario Gdl');
});

it('extracts images from HTML', function () {
    $rawHtml = <<<'HTML'
    <html>
    <body>
        <img src="https://http2.mlstatic.com/D_NQ_NP_photo1.jpg">
        <img src="https://http2.mlstatic.com/D_NQ_NP_photo2.jpg">
    </body>
    </html>
    HTML;

    $extracted = [
        'gallery_images' => [
            'https://http2.mlstatic.com/D_NQ_NP_photo1.jpg',
            'https://http2.mlstatic.com/D_NQ_NP_photo2.jpg',
        ],
    ];

    $result = $this->parser->parse($extracted, $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-123-test_JM');

    expect($result['images'])->toHaveCount(2);
});

it('stores SKU in platform metadata', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {"@type": "Product", "sku": "MLM2698010529"}
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://casa.mercadolibre.com.mx/MLM-2698010529-test_JM');

    expect($result['platform_metadata']['sku'])->toBe('MLM2698010529');
});
