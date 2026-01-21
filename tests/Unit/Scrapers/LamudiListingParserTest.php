<?php

use App\Services\Scrapers\LamudiConfig;
use App\Services\Scrapers\LamudiListingParser;

beforeEach(function () {
    $this->config = new LamudiConfig;
    $this->parser = new LamudiListingParser($this->config);
});

it('parses listing from JSON-LD structured data', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "SingleFamilyResidence",
            "name": "Casa en Renta en Ciudad Granja",
            "description": "Hermosa casa con 7 recámaras y amplio jardín",
            "numberOfBedrooms": 7,
            "numberOfBathroomsTotal": 8,
            "floorSize": {
                "@type": "QuantitativeValue",
                "value": 250,
                "unitCode": "MTK"
            },
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "Calle Poniente 237",
                "addressLocality": "Zapopan",
                "addressRegion": "Jalisco",
                "postalCode": "45030"
            },
            "geo": {
                "@type": "GeoCoordinates",
                "latitude": "20.674429",
                "longitude": "-103.453683"
            },
            "amenityFeature": [
                {"@type": "LocationFeatureSpecification", "name": "Jardín"},
                {"@type": "LocationFeatureSpecification", "name": "Terraza"},
                {"@type": "LocationFeatureSpecification", "name": "Alberca"}
            ],
            "offers": {
                "@type": "Offer",
                "price": 155000,
                "priceCurrency": "MXN"
            }
        }
        </script>
    </head>
    <body>
        <h1>Casa en Renta en Ciudad Granja</h1>
    </body>
    </html>
    HTML;

    $extracted = [
        'title' => 'Casa en Renta en Ciudad Granja',
        'description' => 'Hermosa casa con 7 recámaras y amplio jardín',
    ];

    $result = $this->parser->parse($extracted, $rawHtml, 'https://www.lamudi.com.mx/detalle/41032-73-6153fc4615a4-92a2-19baec7-ab01-7257');

    expect($result)
        ->toHaveKey('external_id')
        ->toHaveKey('operations')
        ->toHaveKey('latitude')
        ->toHaveKey('longitude')
        ->and($result['external_id'])->toBe('41032-73-6153fc4615a4-92a2-19baec7-ab01-7257')
        ->and($result['title'])->toBe('Casa en Renta en Ciudad Granja')
        ->and($result['operations'])->toHaveCount(1)
        ->and($result['operations'][0]['type'])->toBe('rent')
        ->and($result['operations'][0]['price'])->toBe(155000)
        ->and($result['operations'][0]['currency'])->toBe('MXN')
        ->and($result['latitude'])->toBe(20.674429)
        ->and($result['longitude'])->toBe(-103.453683)
        ->and($result['lot_size_m2'])->toBe(250.0)
        ->and($result['bedrooms'])->toBe(7)
        ->and($result['bathrooms'])->toBe(8.0)
        ->and($result['city'])->toBe('Zapopan')
        ->and($result['state'])->toBe('Jalisco')
        ->and($result['amenities'])->toContain('garden')
        ->and($result['amenities'])->toContain('terrace')
        ->and($result['amenities'])->toContain('pool');
});

it('extracts coordinates from JSON-LD geo property', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <script type="application/ld+json">
        {
            "@type": "SingleFamilyResidence",
            "geo": {
                "latitude": "19.4326",
                "longitude": "-99.1332"
            }
        }
        </script>
    </head>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['latitude'])->toBe(19.4326)
        ->and($result['longitude'])->toBe(-99.1332);
});

it('extracts coordinates from meta tags as fallback', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <meta name="ICBM" content="20.6597, -103.3496">
    </head>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['latitude'])->toBe(20.6597)
        ->and($result['longitude'])->toBe(-103.3496);
});

it('extracts coordinates from HTML patterns as fallback', function () {
    $rawHtml = <<<'HTML'
    <html>
    <script>
        var config = {
            lat: 20.674429,
            lng: -103.453683
        };
    </script>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['latitude'])->toBe(20.674429)
        ->and($result['longitude'])->toBe(-103.453683);
});

it('validates coordinates are within Mexico bounds', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <meta name="ICBM" content="40.7128, -74.0060">
    </head>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    // New York coordinates should be rejected
    expect($result['latitude'])->toBeNull()
        ->and($result['longitude'])->toBeNull();
});

it('extracts features from HTML patterns', function () {
    $rawHtml = <<<'HTML'
    <html>
    <body>
        <div>3 recámaras</div>
        <div>2.5 baños</div>
        <div>2 estacionamientos</div>
        <div>Área del terreno: 150 m2</div>
        <div>Área de construcción: 120 m2</div>
    </body>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['bedrooms'])->toBe(3)
        ->and($result['bathrooms'])->toBe(2.5)
        ->and($result['parking_spots'])->toBe(2)
        ->and($result['lot_size_m2'])->toBe(150.0)
        ->and($result['built_size_m2'])->toBe(120.0);
});

it('extracts external ID from URL', function () {
    $result = $this->parser->parse([], '<html></html>', 'https://www.lamudi.com.mx/detalle/41032-73-6153fc4615a4-92a2-19baec7-ab01-7257');

    expect($result['external_id'])->toBe('41032-73-6153fc4615a4-92a2-19baec7-ab01-7257');
});

it('extracts reference codes from HTML', function () {
    $rawHtml = '<html><body>Código: CCR-162-67995</body></html>';

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['external_codes'])->toHaveKey('reference_code')
        ->and($result['external_codes']['reference_code'])->toBe('CCR-162-67995');
});

it('resolves property type from JSON-LD', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {"@type": "Apartment"}
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['property_type'])->toBe('apartment');
});

it('resolves property type from URL as fallback', function () {
    $result = $this->parser->parse([], '<html></html>', 'https://www.lamudi.com.mx/jalisco/departamento-en-renta/detalle/test-id');

    expect($result['property_type'])->toBe('apartment');
});

it('resolves property type from title as fallback', function () {
    $extracted = [
        'title' => 'Departamento en Renta en Providencia',
    ];

    $result = $this->parser->parse($extracted, '<html></html>', 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['property_type'])->toBe('apartment');
});

it('detects property subtype from description', function () {
    $rawHtml = '<html></html>';
    $extracted = [
        'title' => 'Hermoso PENTHOUSE en renta',
    ];

    $result = $this->parser->parse($extracted, $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['property_subtype'])->toBe('penthouse');
});

it('standardizes amenities using config mappings', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {
        "@type": "SingleFamilyResidence",
        "amenityFeature": [
            {"name": "Alberca"},
            {"name": "Gimnasio"},
            {"name": "Seguridad 24h"}
        ]
    }
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['amenities'])->toContain('pool')
        ->and($result['amenities'])->toContain('gym')
        ->and($result['amenities'])->toContain('security_24h');
});

it('extracts location from breadcrumbs as fallback', function () {
    $extracted = [
        'breadcrumbs' => ['Inicio', 'Jalisco', 'Guadalajara', 'Providencia'],
    ];

    $result = $this->parser->parse($extracted, '<html></html>', 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['state'])->toBe('Jalisco')
        ->and($result['city'])->toBe('Guadalajara')
        ->and($result['colonia'])->toBe('Providencia');
});

it('extracts description from meta tag as fallback', function () {
    $rawHtml = <<<'HTML'
    <html>
    <head>
        <meta name="description" content="Casa en Renta de $155,000, 7 recámaras, 8 baños">
    </head>
    </html>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['description'])->toContain('Casa en Renta');
});

it('determines operation type from URL for rent', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {
        "@type": "SingleFamilyResidence",
        "offers": {"price": 15000, "priceCurrency": "MXN"}
    }
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/jalisco/for-rent/detalle/test-id');

    expect($result['operations'][0]['type'])->toBe('rent');
});

it('determines operation type from URL for sale', function () {
    $rawHtml = <<<'HTML'
    <script type="application/ld+json">
    {
        "@type": "SingleFamilyResidence",
        "offers": {"price": 2500000, "priceCurrency": "MXN"}
    }
    </script>
    HTML;

    $result = $this->parser->parse([], $rawHtml, 'https://www.lamudi.com.mx/jalisco/for-sale/detalle/test-id');

    expect($result['operations'][0]['type'])->toBe('sale');
});

it('handles empty HTML gracefully', function () {
    $result = $this->parser->parse([], '', 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result)
        ->toHaveKey('external_id')
        ->toHaveKey('operations')
        ->toHaveKey('images')
        ->and($result['external_id'])->toBe('test-id')
        ->and($result['operations'])->toBeEmpty()
        ->and($result['images'])->toBeEmpty();
});

it('returns correct data structure', function () {
    $result = $this->parser->parse([], '<html></html>', 'https://www.lamudi.com.mx/detalle/test-id');

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
        'publisher_name' => 'Coldwell Banker Deluxe',
    ];

    $result = $this->parser->parse($extracted, '<html></html>', 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['publisher_name'])->toBe('Coldwell Banker Deluxe');
});

it('extracts images from HTML', function () {
    $rawHtml = <<<'HTML'
    <html>
    <body>
        <img src="https://images.lamudi.com.mx/photo1.jpg">
        <img src="https://images.lamudi.com.mx/photo2.jpg">
    </body>
    </html>
    HTML;

    $extracted = [
        'gallery_images' => [
            'https://images.lamudi.com.mx/photo1.jpg',
            'https://images.lamudi.com.mx/photo2.jpg',
        ],
    ];

    $result = $this->parser->parse($extracted, $rawHtml, 'https://www.lamudi.com.mx/detalle/test-id');

    expect($result['images'])->toHaveCount(2);
});
