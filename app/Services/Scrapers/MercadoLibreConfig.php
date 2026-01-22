<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperConfigInterface;

class MercadoLibreConfig implements ScraperConfigInterface
{
    /**
     * CSS extractor configuration for search pages.
     *
     * @return array<string, string>
     */
    public function searchExtractor(): array
    {
        return [
            // Listing URLs - extract from card links (MLM IDs)
            'urls' => 'a[href*="mercadolibre.com.mx/MLM-"] @href',

            // Preview data (shown during discovery)
            'titles' => '[class*="poly-card"] h2, [class*="poly-card"] h3',
            'prices' => '[class*="poly-price"]',
            'locations' => '[class*="poly-card"] [class*="location"], [class*="poly-card"] [class*="address"]',
            'images' => '[class*="poly-card"] img @src',

            // Pagination info
            'page_title' => 'title',
            'h1_title' => 'h1',
            'results_count' => '[class*="results"]',
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
            'description' => '[class*="description"]',
            'price' => '[class*="price"]',

            // Location via breadcrumbs
            'breadcrumbs' => 'nav a',

            // Features from tables
            'features_table' => 'table',

            // Images
            'gallery_images' => 'figure img @src',

            // Publisher info
            'publisher_name' => '[class*="seller"], [class*="agent"]',
            'property_code' => '[class*="code"], [class*="Code"]',

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
            // Coordinate patterns - found in page scripts
            'latitude' => '/["\']?latitude["\']?\s*[:=]\s*["\']?([-\d.]+)/i',
            'longitude' => '/["\']?longitude["\']?\s*[:=]\s*["\']?([-\d.]+)/i',

            // JSON-LD patterns
            'price' => '/"price"\s*:\s*(\d+)/',
            'currency' => '/"priceCurrency"\s*:\s*"([^"]+)"/',
            'sku' => '/"sku"\s*:\s*"(MLM\d+)"/',
            'productID' => '/"productID"\s*:\s*"(MLM\d+)"/',
        ];
    }

    /**
     * URL pattern for extracting external ID.
     * MercadoLibre uses MLM-{number} format: MLM-2698010529
     */
    public function externalIdPattern(): string
    {
        return '/MLM-?(\d+)/i';
    }

    /**
     * Generate paginated URL from base URL and page number.
     * MercadoLibre uses _Desde_{offset} format with 48 items per page.
     */
    public function paginateUrl(string $baseUrl, int $page): string
    {
        // Remove existing _Desde_ parameter if present
        $baseUrl = preg_replace('/_Desde_\d+/', '', $baseUrl);
        // Remove _NoIndex_True if present (will be re-added)
        $baseUrl = preg_replace('/_NoIndex_True/', '', $baseUrl);
        // Clean up trailing slashes and underscores
        $baseUrl = rtrim($baseUrl, '/_');

        if ($page <= 1) {
            return $baseUrl.'/';
        }

        // MercadoLibre uses offset-based pagination (48 items per page)
        $offset = ($page - 1) * 48 + 1;

        return $baseUrl.'/_Desde_'.$offset.'_NoIndex_True';
    }

    /**
     * Extract external ID from a listing URL.
     */
    public function extractExternalId(string $url): ?string
    {
        if (preg_match($this->externalIdPattern(), $url, $matches)) {
            return 'MLM'.$matches[1];
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
            'category' => '/"category"\s*:\s*"([^"]+)"/',
            'item_id' => '/"item_id"\s*:\s*"(MLM\d+)"/',
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
            'casa en condominio' => 'house',
            'departamento' => 'apartment',
            'departamentos' => 'apartment',
            'depto' => 'apartment',
            'terreno' => 'land',
            'terrenos' => 'land',
            'lote' => 'land',
            'local' => 'commercial',
            'locales' => 'commercial',
            'local comercial' => 'commercial',
            'locales comerciales' => 'commercial',
            'oficina' => 'office',
            'oficinas' => 'office',
            'bodega' => 'warehouse',
            'bodegas' => 'warehouse',
            'nave' => 'warehouse',
            'nave industrial' => 'warehouse',
            'rancho' => 'land',
            'quinta' => 'house',
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
            'alquiler' => 'rent',
            'sale' => 'sale',
            'venta' => 'sale',
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
            'inmobiliaria' => 'agency',
            'real_estate_agency' => 'agency',
            'trato directo' => 'individual',
            'private_seller' => 'individual',
            'particular' => 'individual',
        ];
    }

    /**
     * Amenity mappings (Spanish keyword => standardized English name).
     * MercadoLibre has extensive feature tables with Yes/No values.
     *
     * @return array<string, string>
     */
    public function amenityMappings(): array
    {
        return [
            // Ambientes
            'alberca' => 'pool',
            'jardín' => 'garden',
            'jardin' => 'garden',
            'terraza' => 'terrace',
            'balcón' => 'balcony',
            'balcon' => 'balcony',
            'medio baño' => 'half_bathroom',
            'patio' => 'patio',
            'cocina' => 'kitchen',
            'sala' => 'living_room',
            'comedor' => 'dining_room',
            'sala comedor' => 'living_dining',
            'vestidor' => 'walk_in_closet',
            'closets' => 'closets',
            'estudio' => 'study',
            'ático' => 'attic',
            'cuarto de servicio' => 'service_room',
            'cuarto de juegos' => 'game_room',
            'con lavandería' => 'laundry_room',
            'desayunador' => 'breakfast_nook',
            'jacuzzi' => 'jacuzzi',
            'dormitorio principal' => 'master_bedroom',

            // Seguridad
            'alarma' => 'alarm',
            'seguridad' => 'security',
            'portón eléctrico' => 'electric_gate',
            'calefacción' => 'heating',

            // Comodidades y equipamiento
            'chimenea' => 'fireplace',
            'asador' => 'bbq_area',
            'gimnasio' => 'gym',
            'parque infantil' => 'playground',
            'con área verde' => 'green_areas',
            'área de cine' => 'cinema_room',
            'ascensor' => 'elevator',
            'estacionamiento para visitantes' => 'visitor_parking',
            'con cancha de fútbol' => 'soccer_field',
            'cancha de paddle' => 'paddle_court',
            'con cancha polideportiva' => 'sports_court',
            'sauna' => 'sauna',
            'refrigerador' => 'refrigerator',
            'cisterna' => 'water_tank',

            // Servicios
            'acceso a internet' => 'internet',
            'aire acondicionado' => 'ac',
            'tv por cable' => 'cable_tv',
            'línea telefónica' => 'phone_line',
            'gas natural' => 'natural_gas',
            'con conexión para lavarropas' => 'washer_connection',
            'con tv satelital' => 'satellite_tv',
            'con paneles solares' => 'solar_panels',
            'jardinero' => 'gardener',
            'agua corriente' => 'running_water',
            'boiler' => 'water_heater',

            // Condiciones
            'admite mascotas' => 'pet_friendly',
            'amueblado' => 'furnished',
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
            '/CONDOMINIO/i' => 'condo',
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
