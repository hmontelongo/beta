<?php

use App\Services\Scrapers\Inmuebles24Config;
use App\Services\Scrapers\Inmuebles24ListingParser;

beforeEach(function () {
    $this->config = new Inmuebles24Config;
    $this->parser = new Inmuebles24ListingParser($this->config);
});

it('parses a complete listing', function () {
    $extracted = [
        'title' => 'Hermoso Departamento en Providencia',
        'description' => 'Amplio departamento con vista panorámica. 2 recámaras, 2 baños completos.',
        'bedrooms_text' => '3 recámaras',
        'bathrooms_text' => '2 baños',
        'half_bathrooms_text' => '1 medio baño',
        'parking_text' => '2 estacionamientos',
        'area_total_text' => '150 m² de terreno',
        'area_built_text' => '120 m² construidos',
        'age_text' => '5 años',
        'location_header' => 'Providencia, Guadalajara, Jalisco',
        'publisher_name' => 'Inmobiliaria ABC',
        'whatsapp_link' => 'https://wa.me/5213312345678',
        'gallery_images' => [
            'https://cdn.inmuebles24.com/img/360x266/img1.jpg',
            'https://cdn.inmuebles24.com/img/360x266/img2.jpg',
        ],
        'amenities' => ['Alberca', 'Gimnasio', 'Seguridad 24 hrs'],
    ];

    $rawHtml = "
        <script>
        window.dataLayer = [{
            'price': '25000',
            'currencyId': '10',
            'operationTypeId': '2',
            'propertyTypeId': '2',
            'postingId': '12345678',
            'publisherId': '98765',
            'publisherTypeId': '2'
        }];
        var mapConfig = {\"latitude\": 20.6736, \"longitude\": -103.3644};
        </script>
    ";

    $url = 'https://www.inmuebles24.com/propiedades/departamento-12345678.html';

    $result = $this->parser->parse($extracted, $rawHtml, $url);

    expect($result)
        ->toHaveKey('external_id')
        ->toHaveKey('title')
        ->toHaveKey('description')
        ->toHaveKey('bedrooms')
        ->toHaveKey('bathrooms')
        ->toHaveKey('operations')
        ->and($result['external_id'])->toBe('12345678')
        ->and($result['title'])->toBe('Hermoso Departamento en Providencia')
        ->and($result['bedrooms'])->toBe(3)
        ->and($result['bathrooms'])->toBe(2)
        ->and($result['half_bathrooms'])->toBe(1)
        ->and($result['parking_spots'])->toBe(2)
        ->and($result['lot_size_m2'])->toBe(150)
        ->and($result['built_size_m2'])->toBe(120)
        ->and($result['age_years'])->toBe(5)
        ->and($result['latitude'])->toBe(20.6736)
        ->and($result['longitude'])->toBe(-103.3644)
        ->and($result['publisher_name'])->toBe('Inmobiliaria ABC')
        ->and($result['whatsapp'])->toBe('+523312345678'); // 521 prefix normalized to +52
});

it('parses operations from JavaScript variables', function () {
    $extracted = ['title' => 'Test'];

    $rawHtml = "
        <script>
        window.dataLayer = [{
            'price': '1500000',
            'currencyId': '10',
            'operationTypeId': '1'
        }];
        </script>
    ";

    $result = $this->parser->parse($extracted, $rawHtml, 'https://example.com/prop-123456.html');

    expect($result['operations'])->toHaveCount(1)
        ->and($result['operations'][0]['type'])->toBe('sale')
        ->and($result['operations'][0]['price'])->toBe(1500000)
        ->and($result['operations'][0]['currency'])->toBe('MXN');
});

it('parses USD currency correctly', function () {
    $rawHtml = "
        <script>
        window.dataLayer = [{
            'price': '250000',
            'currencyId': '1',
            'operationTypeId': '1'
        }];
        </script>
    ";

    $result = $this->parser->parse(['title' => 'Test'], $rawHtml, 'https://example.com/prop-123456.html');

    expect($result['operations'][0]['currency'])->toBe('USD');
});

it('parses numbers from various text formats', function () {
    $testCases = [
        '3 recámaras' => 3,
        '2 baños' => 2,
        '150 m²' => 150,
        '5 años de antigüedad' => 5,
        'Sin texto numérico aquí' => null,
    ];

    foreach ($testCases as $text => $expected) {
        $extracted = ['bedrooms_text' => $text];
        $result = $this->parser->parse($extracted, '', 'https://www.inmuebles24.com/propiedades/test-123456.html');

        if ($expected === null) {
            expect($result['bedrooms'])->toBeNull("Failed for: {$text}");
        } else {
            expect($result['bedrooms'])->toBe($expected, "Failed for: {$text}");
        }
    }
});

it('extracts external ID from URL', function () {
    $urls = [
        'https://www.inmuebles24.com/propiedades/dep-12345678.html' => '12345678',
        'https://www.inmuebles24.com/propiedad/casa-87654321' => '87654321',
        'https://www.inmuebles24.com/clasificado/oficina-11223344.html' => '11223344',
    ];

    foreach ($urls as $url => $expectedId) {
        $result = $this->parser->parse(['title' => 'Test'], '', $url);
        expect($result['external_id'])->toBe($expectedId, "Failed for: {$url}");
    }
});

it('standardizes amenities', function () {
    $extracted = [
        'title' => 'Test',
        'amenities' => [
            'Alberca',
            'Gimnasio',
            'Seguridad 24 horas',
            'Elevador',
            'Roof Garden',
            'Pet Friendly',
        ],
    ];

    $result = $this->parser->parse($extracted, '', 'https://example.com/prop-123456.html');

    expect($result['amenities'])
        ->toContain('pool')
        ->toContain('gym')
        ->toContain('security_24h')
        ->toContain('elevator')
        ->toContain('roof_garden')
        ->toContain('pet_friendly');
});

it('upgrades image URLs to high resolution', function () {
    $extracted = [
        'title' => 'Test',
        'gallery_images' => [
            'https://cdn.inmuebles24.com/img/360x266/photo1.jpg',
            'https://cdn.inmuebles24.com/img/720x532/photo2.jpg',
        ],
    ];

    $result = $this->parser->parse($extracted, '', 'https://example.com/prop-123456.html');

    expect($result['images'][0])->toContain('1200x1200')
        ->and($result['images'][1])->toContain('1200x1200');
});

it('cleans description text', function () {
    $extracted = [
        'title' => 'Test',
        'description' => "  Hermoso departamento.\n\n\n  Con vista   panorámica.  \t\n",
    ];

    $result = $this->parser->parse($extracted, '', 'https://example.com/prop-123456.html');

    expect($result['description'])->toBe('Hermoso departamento. Con vista panorámica.');
});

it('extracts EasyBroker ID from description', function () {
    $extracted = [
        'title' => 'Test',
        'description' => 'Hermoso departamento. ID de Propiedad: EB-AB1234 en EasyBroker.',
    ];

    $result = $this->parser->parse($extracted, '', 'https://example.com/prop-123456.html');

    expect($result['external_codes']['easybroker'])->toBe('EB-AB1234');
});

it('handles missing data gracefully', function () {
    $extracted = []; // Empty extraction
    $rawHtml = ''; // No JS variables

    // URL must match the external ID pattern (propiedades/propiedad/clasificado)
    $result = $this->parser->parse($extracted, $rawHtml, 'https://www.inmuebles24.com/propiedades/test-123456.html');

    expect($result['external_id'])->toBe('123456')
        ->and($result['title'])->toBeNull()
        ->and($result['bedrooms'])->toBeNull()
        ->and($result['operations'])->toBeArray();
});

it('parses coordinates correctly', function () {
    $rawHtml = '{"latitude": -20.5, "longitude": 103.25}';

    $result = $this->parser->parse(['title' => 'Test'], $rawHtml, 'https://example.com/prop-123456.html');

    expect($result['latitude'])->toBe(-20.5)
        ->and($result['longitude'])->toBe(103.25);
});

it('parses publisher type', function () {
    $rawHtml = "
        <script>
        window.dataLayer = [{
            'publisherTypeId': '2',
            'price': '100000',
            'operationTypeId': '1'
        }];
        </script>
    ";

    $result = $this->parser->parse(['title' => 'Test'], $rawHtml, 'https://example.com/prop-123456.html');

    expect($result['publisher_type'])->toBe('agency');
});

it('detects property subtype from title and description', function () {
    $subtypes = [
        'Penthouse de lujo' => 'penthouse',
        'Departamento PH en venta' => 'penthouse',
        'Garden con terraza' => 'ground_floor',
        'Planta baja amplia' => 'ground_floor',
        'Loft moderno' => 'loft',
        'Duplex familiar' => 'duplex', // Using ASCII version
        'Departamento normal' => null,
    ];

    foreach ($subtypes as $title => $expectedSubtype) {
        // Subtype detection requires a description - it combines title + description
        $result = $this->parser->parse(
            ['title' => $title, 'description' => 'Una propiedad hermosa.'],
            '',
            'https://www.inmuebles24.com/propiedades/test-123456.html'
        );

        if ($expectedSubtype === null) {
            expect($result['property_subtype'])->toBeNull("Failed for: {$title}");
        } else {
            expect($result['property_subtype'])->toBe($expectedSubtype, "Failed for: {$title}");
        }
    }
});

it('extracts whatsapp number from link', function () {
    // Test wa.me link - 13 digits starting with 521 gets normalized
    $extracted = ['whatsapp_link' => 'https://wa.me/5213312345678'];
    $result = $this->parser->parse($extracted, '', 'https://example.com/prop-123456.html');
    expect($result['whatsapp'])->toBe('+523312345678'); // 521 prefix removed and +52 added

    // Test 10-digit local number gets +52 prefix
    $extracted = ['whatsapp_link' => 'https://wa.me/3312345678'];
    $result = $this->parser->parse($extracted, '', 'https://example.com/prop-123456.html');
    expect($result['whatsapp'])->toBe('+523312345678');

    // Test empty link
    $extracted = ['whatsapp_link' => ''];
    $result = $this->parser->parse($extracted, '', 'https://example.com/prop-123456.html');
    expect($result['whatsapp'])->toBeNull();
});

it('handles single amenity as string instead of array', function () {
    // ZenRows may return single value instead of array
    $extracted = [
        'title' => 'Test',
        'amenities' => 'Alberca', // Single string, not array
    ];

    $result = $this->parser->parse($extracted, '', 'https://example.com/prop-123456.html');

    expect($result['amenities'])->toContain('pool');
});

it('parses address from location header', function () {
    $extracted = [
        'title' => 'Test',
        'location_header' => 'Providencia 4ta Sección, Col. Centro, Guadalajara, Jalisco',
    ];

    $result = $this->parser->parse($extracted, '', 'https://example.com/prop-123456.html');

    expect($result['address'])->toBe('Providencia 4ta Sección')
        ->and($result['colonia'])->toBe('Col. Centro')
        ->and($result['city'])->toBe('Guadalajara')
        ->and($result['state'])->toBe('Jalisco');
});

it('handles maintenance included in rent', function () {
    $extracted = [
        'title' => 'Departamento en Renta',
        'description' => '$8,500 mantenimiento incluido',
    ];

    $rawHtml = "
        <script>
        window.dataLayer = [{
            'price': '8500',
            'currencyId': '10',
            'operationTypeId': '2'
        }];
        </script>
        <div>Renta: \$8,500 mantenimiento incluido</div>
    ";

    $result = $this->parser->parse($extracted, $rawHtml, 'https://example.com/prop-123456.html');

    // Maintenance should be null when "mantenimiento incluido" is present
    expect($result['operations'])->toHaveCount(1)
        ->and($result['operations'][0]['price'])->toBe(8500)
        ->and($result['operations'][0]['type'])->toBe('rent')
        ->and($result['operations'][0]['maintenance_fee'])->toBeNull();
});

it('extracts explicit maintenance fee', function () {
    $extracted = [
        'title' => 'Departamento en Renta',
    ];

    $rawHtml = "
        <script>
        window.dataLayer = [{
            'price': '15000',
            'currencyId': '10',
            'operationTypeId': '2'
        }];
        </script>
        <div>Renta: \$15,000</div>
        <div>Mantenimiento: \$2,500</div>
    ";

    $result = $this->parser->parse($extracted, $rawHtml, 'https://example.com/prop-123456.html');

    expect($result['operations'][0]['maintenance_fee'])->toBe(2500);
});
