<?php

use App\Services\Scrapers\VivanunciosConfig;

describe('paginateUrl', function () {
    it('returns base URL for page 1', function () {
        $config = new VivanunciosConfig;
        $baseUrl = 'https://www.vivanuncios.com.mx/s-renta-inmuebles/puerto-vallarta/v1c1098l10594p1';

        expect($config->paginateUrl($baseUrl, 1))->toBe($baseUrl);
    });

    it('inserts page-N/ before last segment for page 2', function () {
        $config = new VivanunciosConfig;
        $baseUrl = 'https://www.vivanuncios.com.mx/s-renta-inmuebles/puerto-vallarta/v1c1098l10594p1';

        $result = $config->paginateUrl($baseUrl, 2);

        expect($result)->toBe('https://www.vivanuncios.com.mx/s-renta-inmuebles/puerto-vallarta/page-2/v1c1098l10594p2');
    });

    it('inserts page-N/ before last segment for page 3', function () {
        $config = new VivanunciosConfig;
        $baseUrl = 'https://www.vivanuncios.com.mx/s-renta-inmuebles/puerto-vallarta/v1c1098l10594p1';

        $result = $config->paginateUrl($baseUrl, 3);

        expect($result)->toBe('https://www.vivanuncios.com.mx/s-renta-inmuebles/puerto-vallarta/page-3/v1c1098l10594p3');
    });

    it('handles URLs with different locations', function () {
        $config = new VivanunciosConfig;
        $baseUrl = 'https://www.vivanuncios.com.mx/s-renta-inmuebles/granja/v1c1098l15143p1';

        $result = $config->paginateUrl($baseUrl, 5);

        expect($result)->toBe('https://www.vivanuncios.com.mx/s-renta-inmuebles/granja/page-5/v1c1098l15143p5');
    });

    it('handles URLs with query strings', function () {
        $config = new VivanunciosConfig;
        $baseUrl = 'https://www.vivanuncios.com.mx/s-renta-inmuebles/puerto-vallarta/v1c1098l10594p1?sort=price';

        $result = $config->paginateUrl($baseUrl, 2);

        expect($result)->toBe('https://www.vivanuncios.com.mx/s-renta-inmuebles/puerto-vallarta/page-2/v1c1098l10594p2?sort=price');
    });

    it('handles high page numbers', function () {
        $config = new VivanunciosConfig;
        $baseUrl = 'https://www.vivanuncios.com.mx/s-renta-inmuebles/puerto-vallarta/v1c1098l10594p1';

        $result = $config->paginateUrl($baseUrl, 13);

        expect($result)->toBe('https://www.vivanuncios.com.mx/s-renta-inmuebles/puerto-vallarta/page-13/v1c1098l10594p13');
    });
});

describe('extractExternalId', function () {
    it('extracts numeric ID from listing URL', function () {
        $config = new VivanunciosConfig;

        expect($config->extractExternalId('https://www.vivanuncios.com.mx/a-venta-casas/puerto-vallarta/casa-en-venta-granja/1234567890'))
            ->toBe('1234567890');
    });

    it('returns null for URLs without numeric ID', function () {
        $config = new VivanunciosConfig;

        expect($config->extractExternalId('https://www.vivanuncios.com.mx/s-renta-inmuebles/'))
            ->toBeNull();
    });
});
