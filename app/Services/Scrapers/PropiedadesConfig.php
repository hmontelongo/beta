<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperConfigInterface;

class PropiedadesConfig implements ScraperConfigInterface
{
    /**
     * CSS extractor configuration for search pages.
     *
     * @return array<string, string>
     */
    public function searchExtractor(): array
    {
        return [
            // Listing URLs - extract from article links
            'urls' => 'article a[href*="/inmuebles/"] @href',

            // Preview data (shown during discovery)
            'titles' => 'article h2',
            'prices' => 'article [class*="bxbIOz"], article [class*="price"]',
            'locations' => 'article [itemprop="addressLocality"]',
            'images' => 'article img[src*="cdn.propiedades.com"] @src',

            // Property and operation type labels
            'property_types' => 'article .section-labels div:first-child',
            'operation_types' => 'article .section-labels div:last-child',

            // Pagination info
            'page_title' => 'title',
            'h1_title' => 'h1',
            'pagination_links' => '.pagination a @href',
            'pagination_numbers' => '.pagination li[data-gtm^="page number"] span, .pagination li[data-gtm^="page number"] a',
        ];
    }

    /**
     * CSS extractor configuration for listing pages.
     *
     * @return array<string, string>
     */
    public function listingExtractor(): array
    {
        return [
            // Core info
            'title' => 'h1',
            'description' => '[class*="description"] p, p[class*="description"]',
            'price' => 'h2 strong, [class*="price"]',

            // Features section
            'features_section' => '[class*="characteristic"], [class*="feature"]',

            // Breadcrumbs for location
            'breadcrumbs' => 'nav li a',

            // Images
            'gallery_images' => 'img[src*="cdn.propiedades.com"] @src',
            'main_image' => 'img[alt*="propiedad"] @src',

            // Publisher info (limited on propiedades.com)
            'publisher_name' => '[class*="seller"], [class*="agent"]',

            // Amenities
            'amenities' => 'h2:contains("Amenidades") ~ div, [class*="amenities"]',

            // JSON-LD structured data
            'json_ld' => 'script[type="application/ld+json"]',

            // Meta tags (coordinates are in meta tags)
            'meta_icbm' => 'meta[name="ICBM"] @content',
            'meta_geo_position' => 'meta[name="geo.position"] @content',
            'meta_geo_region' => 'meta[name="geo.region"] @content',

            // Script extraction for single-request optimization (replaces second raw HTML fetch)
            // next_data: specific selector for Next.js __NEXT_DATA__ (works reliably)
            // all_scripts: fallback for JSON-LD (script[type=...] selector unreliable in ZenRows)
            'next_data' => 'script#__NEXT_DATA__',
            'all_scripts' => 'script',
        ];
    }

    /**
     * Regex patterns for extracting data from JavaScript variables.
     *
     * @return array<string, string>
     */
    public function jsPatterns(): array
    {
        return [
            // __NEXT_DATA__ patterns
            'next_data' => '/<script id="__NEXT_DATA__"[^>]*>(.+?)<\/script>/s',

            // Price patterns
            'price' => '/"price"\s*:\s*(\d+)/',
            'currency' => '/"priceCurrency"\s*:\s*"([^"]+)"/',

            // Location patterns
            'latitude' => '/"latitude"\s*:\s*([-\d.]+)/',
            'longitude' => '/"longitude"\s*:\s*([-\d.]+)/',

            // Property patterns
            'bedrooms' => '/"numberOfBedrooms"\s*:\s*"?(\d+)"?/',
            'bathrooms' => '/"numberOfBathroomsTotal"\s*:\s*"?(\d+)"?/',
            'floor_size' => '/"value"\s*:\s*(\d+).*?"unitText"\s*:\s*"M2"/s',

            // External ID from URL or content
            'external_id' => '/ID:\s*(\d{8})/',
        ];
    }

    /**
     * URL pattern for extracting external ID.
     */
    public function externalIdPattern(): string
    {
        // propiedades.com IDs are 8-digit numbers at the end of the URL slug
        return '/-(\d{8})(?:#|$|\?)/';
    }

    /**
     * Generate paginated URL from base URL and page number.
     */
    public function paginateUrl(string $baseUrl, int $page): string
    {
        // propiedades.com uses ?pagina=N format
        $parsedUrl = parse_url($baseUrl);
        $query = [];

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        $query['pagina'] = $page;

        $baseWithoutQuery = $parsedUrl['scheme'].'://'.$parsedUrl['host'].($parsedUrl['path'] ?? '');

        return $baseWithoutQuery.'?'.http_build_query($query);
    }

    /**
     * Extract external ID from a listing URL.
     */
    public function extractExternalId(string $url): ?string
    {
        if (preg_match($this->externalIdPattern(), $url, $matches)) {
            return $matches[1];
        }

        // Fallback: try to find any 8-digit number at end of URL path
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (preg_match('/(\d{8})(?:$|\/|\?)/', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Patterns for extracting data from dataLayer JavaScript object.
     *
     * @return array<string, string>
     */
    public function dataLayerPatterns(): array
    {
        return [
            // propiedades.com may not use dataLayer, but we include patterns for consistency
            'operation_type' => '/"operationType"\s*:\s*"([^"]+)"/',
            'property_type_text' => '/"propertyType"\s*:\s*"([^"]+)"/',
            'price' => '/"price"\s*:\s*(\d+)/',
            'neighborhood' => '/"neighborhood"\s*:\s*"([^"]+)"/',
            'city' => '/"addressLocality"\s*:\s*"([^"]+)"/',
            'state' => '/"addressRegion"\s*:\s*"([^"]+)"/',
        ];
    }

    /**
     * Map Spanish property type names to standard types.
     *
     * @return array<string, string>
     */
    public function propertyTypeTextMappings(): array
    {
        return [
            'casa' => 'house',
            'casas' => 'house',
            'departamento' => 'apartment',
            'departamentos' => 'apartment',
            'terreno' => 'land',
            'terrenos' => 'land',
            'terreno habitacional' => 'land',
            'terrenos habitacionales' => 'land',
            'local' => 'commercial',
            'locales' => 'commercial',
            'oficina' => 'office',
            'oficinas' => 'office',
            'bodega' => 'warehouse',
            'bodegas' => 'warehouse',
            'bodega comercial' => 'warehouse',
            'bodegas comerciales' => 'warehouse',
            'edificio' => 'building',
            'edificios' => 'building',
            'rancho' => 'ranch',
            'ranchos' => 'ranch',
            'cuarto' => 'room',
            'cuartos' => 'room',
            'casa en condominio' => 'house',
            'casas en condominio' => 'house',
            'penthouse' => 'apartment',
            'loft' => 'apartment',
            'apartment' => 'apartment',
            'house' => 'house',
        ];
    }

    /**
     * Operation type mappings (text => standard type).
     *
     * @return array<int|string, string>
     */
    public function operationTypes(): array
    {
        return [
            'venta' => 'sale',
            'renta' => 'rent',
            'remate' => 'auction',
            'sale' => 'sale',
            'rent' => 'rent',
            'lease' => 'rent',
        ];
    }

    /**
     * Currency type mappings.
     *
     * @return array<int|string, string>
     */
    public function currencyTypes(): array
    {
        return [
            'MXN' => 'MXN',
            'USD' => 'USD',
            'MN' => 'MXN',
            'pesos' => 'MXN',
        ];
    }

    /**
     * Property type mappings (for consistency with interface).
     *
     * @return array<int|string, string>
     */
    public function propertyTypes(): array
    {
        return $this->propertyTypeTextMappings();
    }

    /**
     * Amenity mappings (Spanish keyword => standardized English name).
     *
     * @return array<string, string>
     */
    public function amenityMappings(): array
    {
        return [
            'alberca' => 'pool',
            'piscina' => 'pool',
            'gimnasio' => 'gym',
            'gym' => 'gym',
            'seguridad' => 'security_24h',
            'vigilancia' => 'security_24h',
            'elevador' => 'elevator',
            'ascensor' => 'elevator',
            'roof' => 'roof_garden',
            'terraza' => 'terrace',
            'jardín' => 'garden',
            'jardin' => 'garden',
            'áreas verdes' => 'green_areas',
            'areas verdes' => 'green_areas',
            'pet friendly' => 'pet_friendly',
            'mascotas' => 'pet_friendly',
            'estacionamiento' => 'parking',
            'bodega' => 'storage',
            'cuarto de servicio' => 'service_room',
            'aire acondicionado' => 'ac',
            'calefacción' => 'heating',
            'amueblado' => 'furnished',
            'jacuzzi' => 'jacuzzi',
            'bbq' => 'bbq_area',
            'asador' => 'bbq_area',
            'parrilla' => 'bbq_area',
            'telefonía' => 'phone_line',
            'telefonia' => 'phone_line',
            'internet' => 'internet',
            'cable' => 'cable_tv',
            'closet' => 'closet',
            'cocina integral' => 'integrated_kitchen',
            'área de lavado' => 'laundry_area',
            'balcón' => 'balcony',
            'balcon' => 'balcony',
            'chimenea' => 'fireplace',
            'cisterna' => 'water_tank',
            'gas' => 'gas',
        ];
    }

    /**
     * Property subtype patterns (regex => subtype).
     *
     * @return array<string, string>
     */
    public function subtypePatterns(): array
    {
        return [
            '/\\bPH\\b|PENTHOUSE/i' => 'penthouse',
            '/GARDEN|PLANTA\\s+BAJA|\\bPB\\b/i' => 'ground_floor',
            '/\\bLOFT\\b/i' => 'loft',
            '/DUPLEX|D[ÚU]PLEX/i' => 'duplex',
            '/TRIPLEX/i' => 'triplex',
            '/ESTUDIO/i' => 'studio',
        ];
    }

    /**
     * Additional ZenRows API options for this platform.
     * Propiedades.com requires Mexican proxy for geo-restricted content.
     *
     * @return array<string, mixed>
     */
    public function zenrowsOptions(): array
    {
        return [
            'proxy_country' => 'mx',
        ];
    }
}
