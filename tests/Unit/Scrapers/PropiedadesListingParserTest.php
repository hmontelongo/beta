<?php

use App\Services\Scrapers\PropiedadesConfig;
use App\Services\Scrapers\PropiedadesListingParser;

beforeEach(function () {
    $this->config = new PropiedadesConfig;
    $this->parser = new PropiedadesListingParser($this->config);
});

it('parses listing from JSON-LD structured data', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <meta name="ICBM" content="20.15402, -103.914399">
        <meta name="geo.position" content="20.15402;-103.914399">
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "RealEstateListing",
            "offers": {
                "@type": "Offer",
                "price": 13500,
                "priceCurrency": "MXN",
                "businessFunction": "https://schema.org/LeaseOut",
                "itemOffered": {
                    "@type": "House",
                    "name": "Casa en Renta",
                    "description": "Hermosa casa en renta con 3 recámaras",
                    "numberOfBathroomsTotal": 2,
                    "floorSize": {
                        "@type": "QuantitativeValue",
                        "value": 120,
                        "unitText": "M2"
                    },
                    "address": {
                        "@type": "PostalAddress",
                        "streetAddress": "Senderos de Monte Verde",
                        "addressLocality": "Tlajomulco de Zúñiga",
                        "addressRegion": "Jalisco",
                        "postalCode": "45646"
                    },
                    "amenityFeature": [
                        {"@type": "LocationFeatureSpecification", "name": "estacionamiento", "value": true},
                        {"@type": "LocationFeatureSpecification", "name": "aire acondicionado", "value": true}
                    ]
                }
            }
        }
        </script>
    </head>
    <body>
        <h1>Renta de casa Senderos de Monte Verde</h1>
    </body>
    </html>
    HTML;

    $extracted = [
        'title' => 'Renta de casa Senderos de Monte Verde',
        'description' => 'Hermosa casa en renta con 3 recámaras',
    ];

    $result = $this->parser->parse($extracted, $rawHtml, 'https://propiedades.com/inmuebles/casa-30554556');

    expect($result)
        ->toHaveKey('external_id')
        ->toHaveKey('operations')
        ->toHaveKey('latitude')
        ->toHaveKey('longitude')
        ->and($result['external_id'])->toBe('30554556')
        ->and($result['title'])->toBe('Renta de casa Senderos de Monte Verde')
        ->and($result['operations'])->toHaveCount(1)
        ->and($result['operations'][0]['type'])->toBe('rent')
        ->and($result['operations'][0]['price'])->toBe(13500)
        ->and($result['operations'][0]['currency'])->toBe('MXN')
        ->and($result['latitude'])->toBe(20.15402)
        ->and($result['longitude'])->toBe(-103.914399)
        ->and($result['lot_size_m2'])->toBe(120.0)
        ->and($result['bathrooms'])->toBe(2.0)
        ->and($result['city'])->toBe('Tlajomulco de Zúñiga')
        ->and($result['state'])->toBe('Jalisco')
        ->and($result['amenities'])->toContain('parking')
        ->and($result['amenities'])->toContain('ac');
});

it('extracts coordinates from meta tags', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <meta name="ICBM" content="19.4326, -99.1332">
    </head>
    <body></body>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://propiedades.com/inmuebles/casa-12345678');

    expect($result['latitude'])->toBe(19.4326)
        ->and($result['longitude'])->toBe(-99.1332);
});

it('extracts coordinates from geo.position meta tag', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <meta name="geo.position" content="20.6597;-103.3496">
    </head>
    <body></body>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://propiedades.com/inmuebles/casa-12345678');

    expect($result['latitude'])->toBe(20.6597)
        ->and($result['longitude'])->toBe(-103.3496);
});

it('validates coordinates are within Mexico bounds', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <meta name="ICBM" content="40.7128, -74.0060">
    </head>
    <body></body>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://propiedades.com/inmuebles/casa-12345678');

    // New York coordinates should be rejected
    expect($result['latitude'])->toBeNull()
        ->and($result['longitude'])->toBeNull();
});

it('extracts features from HTML patterns', function () {
    $rawHtml = <<<'HTML'
    <html>
    <body>
        <div>BAÑOS 3</div>
        <div>ESTACIONAMIENTOS 2</div>
        <div>ÁREA TERRENO 150 m2</div>
        <div>ÁREA CONSTRUIDA 120 m2</div>
        <div>Edad del inmueble 5 años</div>
        <div>RECÁMARAS 4</div>
    </body>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://propiedades.com/inmuebles/casa-12345678');

    expect($result['bathrooms'])->toBe(3.0)
        ->and($result['parking_spots'])->toBe(2)
        ->and($result['lot_size_m2'])->toBe(150.0)
        ->and($result['built_size_m2'])->toBe(120.0)
        ->and($result['age_years'])->toBe(5)
        ->and($result['bedrooms'])->toBe(4);
});

it('extracts external ID from URL', function () {
    $result = $this->parser->parse([], '<html></html>', 'https://propiedades.com/inmuebles/casa-en-renta-example-30554556');

    expect($result['external_id'])->toBe('30554556');
});

it('extracts external ID from HTML content as fallback', function () {
    $rawHtml = '<html><body>ID: 30554556</body></html>';

    $result = $this->parser->parse([], $rawHtml, 'https://propiedades.com/inmuebles/no-id-in-url');

    expect($result['external_id'])->toBe('30554556');
});

it('extracts images and deduplicates by UUID', function () {
    $rawHtml = <<<'HTML'
    <html>
    <body>
        <img src="https://cdn.propiedades.com/files/600x400/eb95a609-d30c-11f0-9755-2bd181ff1149.jpeg">
        <img src="https://cdn.propiedades.com/files/1200x507/eb95a609-d30c-11f0-9755-2bd181ff1149.jpeg">
        <img src="https://cdn.propiedades.com/files/600x400/209bbb84-d30d-11f0-a17e-2bd181ff1149.jpeg">
    </body>
    </html>
    HTML;

    $extracted = [
        'gallery_images' => [
            'https://cdn.propiedades.com/files/600x400/eb95a609-d30c-11f0-9755-2bd181ff1149.jpeg',
        ],
    ];

    $result = $this->parser->parse($extracted, $rawHtml, 'https://propiedades.com/inmuebles/casa-12345678');

    // Should dedupe by UUID and upgrade resolution
    expect($result['images'])->toHaveCount(2)
        ->and($result['images'][0])->toContain('1200x507'); // Upgraded resolution
});

it('resolves property type from JSON-LD', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {"@type": "RealEstateListing", "offers": {"itemOffered": {"@type": "Apartment"}}}
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://propiedades.com/inmuebles/depa-12345678');

    expect($result['property_type'])->toBe('apartment');
});

it('resolves property type from URL as fallback', function () {
    $result = $this->parser->parse([], '<html></html>', 'https://propiedades.com/inmuebles/departamento-en-renta-12345678');

    expect($result['property_type'])->toBe('apartment');
});

it('detects property subtype from description', function () {
    $rawHtml = '<html></html>';
    $extracted = [
        'title' => 'Hermoso PENTHOUSE en renta',
    ];

    $result = $this->parser->parse($extracted, $rawHtml, 'https://propiedades.com/inmuebles/depa-12345678');

    expect($result['property_subtype'])->toBe('penthouse');
});

it('standardizes amenities using config mappings', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {
        "@type": "RealEstateListing",
        "offers": {
            "itemOffered": {
                "amenityFeature": [
                    {"name": "alberca", "value": true},
                    {"name": "gimnasio", "value": true},
                    {"name": "seguridad 24h", "value": true}
                ]
            }
        }
    }
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://propiedades.com/inmuebles/casa-12345678');

    expect($result['amenities'])->toContain('pool')
        ->and($result['amenities'])->toContain('gym')
        ->and($result['amenities'])->toContain('security_24h');
});

it('extracts location from breadcrumbs as fallback', function () {
    $extracted = [
        'breadcrumbs' => ['Inicio', 'Jalisco', 'Guadalajara', 'Providencia'],
    ];

    $result = $this->parser->parse($extracted, '<html></html>', 'https://propiedades.com/inmuebles/casa-12345678');

    expect($result['state'])->toBe('Jalisco')
        ->and($result['city'])->toBe('Guadalajara');
});

it('extracts description from meta tag as fallback', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <meta name="description" content="Casa en Renta de $13,500, 3 recámaras, 2 baños">
    </head>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://propiedades.com/inmuebles/casa-12345678');

    expect($result['description'])->toContain('Casa en Renta');
});

it('handles empty HTML gracefully', function () {
    $result = $this->parser->parse([], '', 'https://propiedades.com/inmuebles/casa-12345678');

    expect($result)
        ->toHaveKey('external_id')
        ->toHaveKey('operations')
        ->toHaveKey('images')
        ->and($result['external_id'])->toBe('12345678')
        ->and($result['operations'])->toBeEmpty()
        ->and($result['images'])->toBeEmpty();
});

it('returns correct data structure', function () {
    $result = $this->parser->parse([], '<html></html>', 'https://propiedades.com/inmuebles/casa-12345678');

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
