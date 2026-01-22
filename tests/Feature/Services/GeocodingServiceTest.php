<?php

use App\Services\Google\GeocodingService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.google.maps_api_key' => 'test-api-key']);
    config(['services.google.geocoding_enabled' => true]);
});

describe('geocode', function () {
    it('falls back to reverse geocoding when forward geocode returns no colonia', function () {
        // Mock forward geocode returning no colonia (route-level match)
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json?address=*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'geometry' => [
                        'location' => ['lat' => 20.677, 'lng' => -103.447],
                        'location_type' => 'GEOMETRIC_CENTER',
                    ],
                    'formatted_address' => 'Calz. Central, Zapopan, Jal., México',
                    'place_id' => 'test_place_id',
                    'address_components' => [
                        ['long_name' => 'Calzada Central', 'types' => ['route']],
                        ['long_name' => 'Zapopan', 'types' => ['locality', 'political']],
                        ['long_name' => 'Jalisco', 'types' => ['administrative_area_level_1', 'political']],
                        ['long_name' => 'México', 'types' => ['country', 'political']],
                    ],
                ]],
            ]),
            // Mock reverse geocode returning colonia
            'maps.googleapis.com/maps/api/geocode/json?latlng=*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'address_components' => [
                        ['long_name' => '1160', 'types' => ['street_number']],
                        ['long_name' => 'Calzada Central', 'types' => ['route']],
                        ['long_name' => 'Granja', 'types' => ['sublocality_level_1', 'sublocality', 'political']],
                        ['long_name' => 'Zapopan', 'types' => ['locality', 'political']],
                        ['long_name' => 'Jalisco', 'types' => ['administrative_area_level_1', 'political']],
                        ['long_name' => '45010', 'types' => ['postal_code']],
                        ['long_name' => 'México', 'types' => ['country', 'political']],
                    ],
                ]],
            ]),
        ]);

        $service = new GeocodingService;
        $result = $service->geocode('Calzada Central', 'Zapopan', 'Jalisco');

        expect($result)->not->toBeNull()
            ->and($result['colonia'])->toBe('Granja')
            ->and($result['postal_code'])->toBe('45010')
            ->and($result['city'])->toBe('Zapopan')
            ->and($result['state'])->toBe('Jalisco');

        // Verify both API calls were made
        Http::assertSentCount(2);
    });

    it('does not reverse geocode when forward geocode returns colonia', function () {
        // Mock forward geocode returning colonia (precise match)
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json?address=*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'geometry' => [
                        'location' => ['lat' => 20.677, 'lng' => -103.447],
                        'location_type' => 'ROOFTOP',
                    ],
                    'formatted_address' => 'Calz. Central 293, La Calma, 45010 Zapopan, Jal., México',
                    'place_id' => 'test_place_id',
                    'address_components' => [
                        ['long_name' => '293', 'types' => ['street_number']],
                        ['long_name' => 'Calzada Central', 'types' => ['route']],
                        ['long_name' => 'La Calma', 'types' => ['sublocality_level_1', 'sublocality', 'political']],
                        ['long_name' => 'Zapopan', 'types' => ['locality', 'political']],
                        ['long_name' => 'Jalisco', 'types' => ['administrative_area_level_1', 'political']],
                        ['long_name' => '45010', 'types' => ['postal_code']],
                        ['long_name' => 'México', 'types' => ['country', 'political']],
                    ],
                ]],
            ]),
        ]);

        $service = new GeocodingService;
        $result = $service->geocode('Calzada Central 293', 'Zapopan', 'Jalisco');

        expect($result)->not->toBeNull()
            ->and($result['colonia'])->toBe('La Calma')
            ->and($result['postal_code'])->toBe('45010');

        // Only forward geocode should be called, no reverse
        Http::assertSentCount(1);
    });

    it('preserves forward geocode data when reverse geocode fails', function () {
        // Mock forward geocode returning no colonia
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json?address=*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'geometry' => [
                        'location' => ['lat' => 20.677, 'lng' => -103.447],
                        'location_type' => 'GEOMETRIC_CENTER',
                    ],
                    'formatted_address' => 'Calz. Central, Zapopan, Jal., México',
                    'place_id' => 'test_place_id',
                    'address_components' => [
                        ['long_name' => 'Calzada Central', 'types' => ['route']],
                        ['long_name' => 'Zapopan', 'types' => ['locality', 'political']],
                        ['long_name' => 'Jalisco', 'types' => ['administrative_area_level_1', 'political']],
                    ],
                ]],
            ]),
            // Mock reverse geocode failing
            'maps.googleapis.com/maps/api/geocode/json?latlng=*' => Http::response([
                'status' => 'ZERO_RESULTS',
                'results' => [],
            ]),
        ]);

        $service = new GeocodingService;
        $result = $service->geocode('Calzada Central', 'Zapopan', 'Jalisco');

        // Should still return result with coordinates, but null colonia
        expect($result)->not->toBeNull()
            ->and($result['lat'])->toBe(20.677)
            ->and($result['lng'])->toBe(-103.447)
            ->and($result['colonia'])->toBeNull()
            ->and($result['city'])->toBe('Zapopan')
            ->and($result['state'])->toBe('Jalisco');
    });

    it('fills in postal code from reverse geocode when missing from forward', function () {
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json?address=*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'geometry' => [
                        'location' => ['lat' => 20.677, 'lng' => -103.447],
                        'location_type' => 'GEOMETRIC_CENTER',
                    ],
                    'formatted_address' => 'Calz. Central, Zapopan, Jal., México',
                    'place_id' => 'test_place_id',
                    'address_components' => [
                        ['long_name' => 'Calzada Central', 'types' => ['route']],
                        ['long_name' => 'Zapopan', 'types' => ['locality', 'political']],
                        ['long_name' => 'Jalisco', 'types' => ['administrative_area_level_1', 'political']],
                    ],
                ]],
            ]),
            'maps.googleapis.com/maps/api/geocode/json?latlng=*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'address_components' => [
                        ['long_name' => 'Granja', 'types' => ['sublocality_level_1']],
                        ['long_name' => 'Zapopan', 'types' => ['locality']],
                        ['long_name' => 'Jalisco', 'types' => ['administrative_area_level_1']],
                        ['long_name' => '45010', 'types' => ['postal_code']],
                    ],
                ]],
            ]),
        ]);

        $service = new GeocodingService;
        $result = $service->geocode('Calzada Central', 'Zapopan', 'Jalisco');

        expect($result['colonia'])->toBe('Granja')
            ->and($result['postal_code'])->toBe('45010');
    });

    it('does not overwrite postal code from forward geocode', function () {
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json?address=*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'geometry' => [
                        'location' => ['lat' => 20.677, 'lng' => -103.447],
                        'location_type' => 'GEOMETRIC_CENTER',
                    ],
                    'formatted_address' => 'Calz. Central, Zapopan, Jal., México',
                    'place_id' => 'test_place_id',
                    'address_components' => [
                        ['long_name' => 'Calzada Central', 'types' => ['route']],
                        ['long_name' => 'Zapopan', 'types' => ['locality', 'political']],
                        ['long_name' => 'Jalisco', 'types' => ['administrative_area_level_1', 'political']],
                        ['long_name' => '45000', 'types' => ['postal_code']],
                    ],
                ]],
            ]),
            'maps.googleapis.com/maps/api/geocode/json?latlng=*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'address_components' => [
                        ['long_name' => 'Granja', 'types' => ['sublocality_level_1']],
                        ['long_name' => '45010', 'types' => ['postal_code']],
                    ],
                ]],
            ]),
        ]);

        $service = new GeocodingService;
        $result = $service->geocode('Calzada Central', 'Zapopan', 'Jalisco');

        // Should keep forward geocode postal code, not overwrite with reverse
        expect($result['colonia'])->toBe('Granja')
            ->and($result['postal_code'])->toBe('45000');
    });
});

describe('reverseGeocode', function () {
    it('returns structured address components from coordinates', function () {
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json?latlng=*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'address_components' => [
                        ['long_name' => '1160', 'types' => ['street_number']],
                        ['long_name' => 'Calzada Central', 'types' => ['route']],
                        ['long_name' => 'Granja', 'types' => ['sublocality_level_1', 'sublocality', 'political']],
                        ['long_name' => 'Zapopan', 'types' => ['locality', 'political']],
                        ['long_name' => 'Jalisco', 'types' => ['administrative_area_level_1', 'political']],
                        ['long_name' => '45010', 'types' => ['postal_code']],
                    ],
                ]],
            ]),
        ]);

        $service = new GeocodingService;
        $result = $service->reverseGeocode(20.677, -103.447);

        expect($result)->not->toBeNull()
            ->and($result['colonia'])->toBe('Granja')
            ->and($result['city'])->toBe('Zapopan')
            ->and($result['state'])->toBe('Jalisco')
            ->and($result['postal_code'])->toBe('45010');
    });

    it('returns null when geocoding is disabled', function () {
        config(['services.google.geocoding_enabled' => false]);

        $service = new GeocodingService;
        $result = $service->reverseGeocode(20.677, -103.447);

        expect($result)->toBeNull();
    });

    it('returns null when API returns no results', function () {
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json?latlng=*' => Http::response([
                'status' => 'ZERO_RESULTS',
                'results' => [],
            ]),
        ]);

        $service = new GeocodingService;
        $result = $service->reverseGeocode(20.677, -103.447);

        expect($result)->toBeNull();
    });
});
