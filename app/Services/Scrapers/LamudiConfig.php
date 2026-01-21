<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperConfigInterface;

class LamudiConfig implements ScraperConfigInterface
{
    /**
     * CSS extractor configuration for search pages.
     *
     * @return array<string, string>
     */
    public function searchExtractor(): array
    {
        return [
            // Listing URLs - extract from card links
            'urls' => 'a[href*="/detalle/"] @href',

            // Preview data (shown during discovery)
            'titles' => '[class*="card"] h2, [class*="Card"] h2, [class*="title"]',
            'prices' => '[class*="price"], [class*="Price"]',
            'locations' => '[class*="location"], [class*="address"]',
            'images' => '[class*="card"] img @src',

            // Pagination info
            'page_title' => 'title',
            'h1_title' => 'h1',
            'pagination_text' => '[class*="pagination"]',
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
            'description' => '[class*="description"], [class*="Description"]',
            'price' => '[class*="price"], [class*="Price"]',

            // Location
            'location' => '[class*="location"], [class*="address"]',
            'breadcrumbs' => 'nav a, [class*="breadcrumb"] a',

            // Images
            'gallery_images' => '[class*="gallery"] img @src, [class*="carousel"] img @src, [class*="slider"] img @src',

            // Publisher info
            'publisher_name' => '[class*="agent"] a, [class*="seller"] a, [class*="contact"] a',

            // Meta tags for coordinates
            'meta_description' => 'meta[name="description"] @content',
            'meta_icbm' => 'meta[name="ICBM"] @content',
            'meta_geo_position' => 'meta[name="geo.position"] @content',

            // Script extraction for single-request optimization
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
            // Coordinate patterns
            'latitude' => '/lat(?:itude)?["\']?\s*[:=]\s*["\']?([-\d.]+)/i',
            'longitude' => '/l(?:ng|on(?:gitude)?)["\']?\s*[:=]\s*["\']?([-\d.]+)/i',

            // Price patterns
            'price' => '/"price"\s*:\s*(\d+)/',
            'currency' => '/"priceCurrency"\s*:\s*"([^"]+)"/',

            // Reference code
            'reference_code' => '/CCR-[\d-]+/',
        ];
    }

    /**
     * URL pattern for extracting external ID.
     * Lamudi uses complex IDs like: 41032-73-6153fc4615a4-92a2-19baec7-ab01-7257
     */
    public function externalIdPattern(): string
    {
        return '/\/detalle\/([a-z0-9-]+)$/i';
    }

    /**
     * Generate paginated URL from base URL and page number.
     * Lamudi uses ?page=N format.
     */
    public function paginateUrl(string $baseUrl, int $page): string
    {
        $parsedUrl = parse_url($baseUrl);
        $query = [];

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        $query['page'] = $page;

        $baseWithoutQuery = ($parsedUrl['scheme'] ?? 'https').'://'.($parsedUrl['host'] ?? '').($parsedUrl['path'] ?? '');

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
            // Lamudi may not use dataLayer extensively
            'property_type' => '/"propertyType"\s*:\s*"([^"]+)"/',
            'operation_type' => '/"operationType"\s*:\s*"([^"]+)"/',
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
            'casa unifamiliar' => 'house',
            'casa en condominio' => 'house',
            'singlefamilyresidence' => 'house',
            'departamento' => 'apartment',
            'departamentos' => 'apartment',
            'apartment' => 'apartment',
            'terreno' => 'land',
            'terrenos' => 'land',
            'lote' => 'land',
            'lote / terreno' => 'land',
            'local' => 'commercial',
            'locales' => 'commercial',
            'tienda / local comercial' => 'commercial',
            'local comercial' => 'commercial',
            'oficina' => 'office',
            'oficinas' => 'office',
            'bodega' => 'warehouse',
            'bodegas' => 'warehouse',
            'nave industrial' => 'warehouse',
            'nave industrial / bodega' => 'warehouse',
            'edificio' => 'building',
            'edificios' => 'building',
            'estudio' => 'apartment',
            'estudios' => 'apartment',
            'penthouse' => 'apartment',
            'loft' => 'apartment',
        ];
    }

    /**
     * Operation type mappings.
     *
     * @return array<int|string, string>
     */
    public function operationTypes(): array
    {
        return [
            'rent' => 'rent',
            'renta' => 'rent',
            'for-rent' => 'rent',
            'sale' => 'sale',
            'venta' => 'sale',
            'for-sale' => 'sale',
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
     * Publisher type mappings.
     *
     * @return array<int|string, string>
     */
    public function publisherTypes(): array
    {
        return [
            'agency' => 'agency',
            'agencia' => 'agency',
            'individual' => 'individual',
            'particular' => 'individual',
            'developer' => 'developer',
            'desarrollador' => 'developer',
        ];
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
            'roof garden' => 'roof_garden',
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
            'sin amueblar' => 'unfurnished',
            'jacuzzi' => 'jacuzzi',
            'bbq' => 'bbq_area',
            'asador' => 'bbq_area',
            'parrilla' => 'bbq_area',
            'cocina integral' => 'integrated_kitchen',
            'área de lavado' => 'laundry_area',
            'balcón' => 'balcony',
            'balcon' => 'balcony',
            'chimenea' => 'fireplace',
            'cisterna' => 'water_tank',
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
     *
     * @return array<string, mixed>
     */
    public function zenrowsOptions(): array
    {
        return [];
    }
}
