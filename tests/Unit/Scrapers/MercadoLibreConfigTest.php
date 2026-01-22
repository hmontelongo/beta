<?php

use App\Services\Scrapers\MercadoLibreConfig;

beforeEach(function () {
    $this->config = new MercadoLibreConfig;
});

it('generates paginated URLs with offset', function () {
    $baseUrl = 'https://inmuebles.mercadolibre.com.mx/casas/renta/jalisco/zapopan';

    expect($this->config->paginateUrl($baseUrl, 1))->toBe('https://inmuebles.mercadolibre.com.mx/casas/renta/jalisco/zapopan/')
        ->and($this->config->paginateUrl($baseUrl, 2))->toBe('https://inmuebles.mercadolibre.com.mx/casas/renta/jalisco/zapopan/_Desde_49_NoIndex_True')
        ->and($this->config->paginateUrl($baseUrl, 3))->toBe('https://inmuebles.mercadolibre.com.mx/casas/renta/jalisco/zapopan/_Desde_97_NoIndex_True');
});

it('removes existing pagination from URL', function () {
    $url = 'https://inmuebles.mercadolibre.com.mx/casas/renta/_Desde_49_NoIndex_True';

    expect($this->config->paginateUrl($url, 1))->toBe('https://inmuebles.mercadolibre.com.mx/casas/renta/')
        ->and($this->config->paginateUrl($url, 3))->toBe('https://inmuebles.mercadolibre.com.mx/casas/renta/_Desde_97_NoIndex_True');
});

it('extracts external ID from URL with hyphen', function () {
    $url = 'https://casa.mercadolibre.com.mx/MLM-2698010529-casa-en-renta_JM';

    expect($this->config->extractExternalId($url))->toBe('MLM2698010529');
});

it('extracts external ID from URL without hyphen', function () {
    $url = 'https://casa.mercadolibre.com.mx/MLM2698010529-casa-en-renta_JM';

    expect($this->config->extractExternalId($url))->toBe('MLM2698010529');
});

it('returns null for invalid URL', function () {
    $url = 'https://example.com/invalid-url';

    expect($this->config->extractExternalId($url))->toBeNull();
});

it('has search extractor configuration', function () {
    $extractor = $this->config->searchExtractor();

    expect($extractor)
        ->toHaveKey('urls')
        ->toHaveKey('titles')
        ->toHaveKey('prices')
        ->toHaveKey('locations')
        ->toHaveKey('images')
        ->toHaveKey('h1_title');
});

it('has listing extractor configuration', function () {
    $extractor = $this->config->listingExtractor();

    expect($extractor)
        ->toHaveKey('title')
        ->toHaveKey('description')
        ->toHaveKey('price')
        ->toHaveKey('gallery_images')
        ->toHaveKey('publisher_name')
        ->toHaveKey('all_scripts');
});

it('has JavaScript patterns for coordinate extraction', function () {
    $patterns = $this->config->jsPatterns();

    expect($patterns)
        ->toHaveKey('latitude')
        ->toHaveKey('longitude')
        ->toHaveKey('price')
        ->toHaveKey('sku');
});

it('has property type mappings', function () {
    $mappings = $this->config->propertyTypes();

    expect($mappings)
        ->toHaveKey('casa')
        ->toHaveKey('departamento')
        ->toHaveKey('terreno')
        ->and($mappings['casa'])->toBe('house')
        ->and($mappings['departamento'])->toBe('apartment')
        ->and($mappings['terreno'])->toBe('land');
});

it('has operation type mappings', function () {
    $mappings = $this->config->operationTypes();

    expect($mappings)
        ->toHaveKey('renta')
        ->toHaveKey('venta')
        ->and($mappings['renta'])->toBe('rent')
        ->and($mappings['venta'])->toBe('sale');
});

it('has amenity mappings for MercadoLibre features', function () {
    $mappings = $this->config->amenityMappings();

    expect($mappings)
        ->toHaveKey('alberca')
        ->toHaveKey('gimnasio')
        ->toHaveKey('jardín')
        ->toHaveKey('aire acondicionado')
        ->and($mappings['alberca'])->toBe('pool')
        ->and($mappings['gimnasio'])->toBe('gym')
        ->and($mappings['jardín'])->toBe('garden')
        ->and($mappings['aire acondicionado'])->toBe('ac');
});

it('has subtype patterns', function () {
    $patterns = $this->config->subtypePatterns();

    expect($patterns)->not->toBeEmpty()
        ->and(array_values($patterns))->toContain('penthouse')
        ->and(array_values($patterns))->toContain('loft')
        ->and(array_values($patterns))->toContain('duplex');
});
