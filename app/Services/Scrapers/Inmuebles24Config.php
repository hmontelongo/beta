<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperConfigInterface;

class Inmuebles24Config implements ScraperConfigInterface
{
    /**
     * CSS extractor configuration for search pages.
     *
     * @return array<string, string>
     */
    public function searchExtractor(): array
    {
        return [
            // Listing URLs - extract href attributes
            'urls' => '[data-qa^="posting"] a[href*="/propiedades/"] @href',

            // Preview data (shown during discovery)
            'titles' => '[data-qa="POSTING_CARD_DESCRIPTION"]',
            'prices' => '[data-qa="POSTING_CARD_PRICE"]',
            'locations' => '[data-qa="POSTING_CARD_LOCATION"]',
            'images' => '[data-qa^="posting"] img @src',

            // Pagination - extract title for total count
            'page_title' => 'title',
            'page_links' => '[data-qa^="PAGING_"] @data-qa',
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
            'description' => '#longDescription, [class*="descriptionContent"], [data-qa="POSTING_DESCRIPTION"]',

            // Features (icon-based elements)
            'bedrooms_text' => '.icon-dormitorio',
            'bathrooms_text' => '.icon-bano',
            'half_bathrooms_text' => '.icon-toilete',
            'parking_text' => '.icon-cochera',
            'area_total_text' => '.icon-stotal',
            'area_built_text' => '.icon-scubierta',
            'age_text' => '.icon-antiguedad',

            // All feature items for fallback parsing
            'feature_items' => 'li.icon-feature',

            // Location
            'location_header' => '.section-location-property h4',
            'breadcrumbs' => '[class*="breadcrumb"] a',

            // Images - multiple selectors for fallback (also grab data-src for lazy loading)
            'gallery_images' => '[class*="gallery"] img @src, [class*="gallery"] img @data-src',
            'carousel_images' => '[class*="carousel"] img @src, [class*="carousel"] img @data-src',
            'picture_images' => '[class*="picture"] img @src, [class*="picture"] img @data-src',
            'multimedia_images' => '[class*="multimedia"] img @src, [data-qa*="GALLERY"] img @src',
            'all_listing_images' => '[class*="posting-image"] img @src, [class*="PostingImage"] img @src',
            'preview_gallery_images' => '[class*="preview-gallery"] img @src, [class*="preview-gallery"] img @data-src',
            'modal_gallery_images' => '[class*="modal"] img @src, [class*="lightbox"] img @src, [class*="fullscreen"] img @src',

            // Publisher info
            'publisher_name' => '[data-qa="publisher-name"], [class*="publisher-name"]',
            'whatsapp_link' => 'a[href*="wa.me"] @href',

            // Stats
            'stats_text' => '.view-users-container',

            // Amenities - use flexible selector since CSS module classes change
            'amenities' => '[class*="generalFeatures"] [class*="description"], [class*="amenities"] li, [data-qa*="AMENITY"]',

            // Price containers for dual operations
            'price_containers' => '[data-qa="POSTING_CARD_PRICE"], [class*="price-value"]',
            'operation_tags' => '[data-qa="POSTING_CARD_FEATURES"] span, [class*="operation-type"]',
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
            'price' => "/'price':\s*'(\d+)'/",
            'currency_id' => "/'currencyId':\s*'(\d+)'/",
            'operation_type_id' => "/'operationTypeId':\s*'(\d+)'/",
            'province_id' => "/'provinceId':\s*'(\d+)'/",
            'city_id' => "/'cityId':\s*'(\d+)'/",
            'neighborhood_id' => "/'neighborhoodId':\s*'(\d+)'/",
            'property_type_id' => "/'propertyTypeId':\s*'(\d+)'/",
            'posting_id' => "/'postingId':\s*'(\d+)'/",
            'publisher_id' => "/'publisherId':\s*'(\d+)'/",
            'publisher_name' => "/'name':\s*'([^']+)'/",
            'publisher_type_id' => "/'publisherTypeId':\s*'(\d+)'/",
            'publisher_url' => "/'url':\s*'(\\/(?:inmobiliaria|agencia|desarrolladora)[^']+)'/",
            'publisher_logo' => "/'urlLogo':\s*'([^']+)'/",
            'whatsapp' => "/'whatsApp':\s*'([^']+)'/",
            'latitude' => '/"latitude":\s*([-\d.]+)/',
            'longitude' => '/"longitude":\s*([-\d.]+)/',
        ];
    }

    /**
     * URL pattern for extracting external ID.
     */
    public function externalIdPattern(): string
    {
        return '/(?:propiedades|propiedad|clasificado)[\\/-].*?(\\d{6,})/';
    }

    /**
     * Generate paginated URL from base URL and page number.
     */
    public function paginateUrl(string $baseUrl, int $page): string
    {
        // Inmuebles24 uses -pagina-N format
        if (preg_match('/-pagina-\d+/', $baseUrl)) {
            return preg_replace('/-pagina-\d+/', "-pagina-{$page}", $baseUrl);
        }

        // Add pagination to URL
        return rtrim($baseUrl, '/')."-pagina-{$page}";
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
            'operation_type' => '/"tipoDeOperacion"\s*:\s*"([^"]+)"/',
            'property_type_text' => '/"tipoDePropiedad"\s*:\s*"([^"]+)"/',
            'sale_price' => '/"precioVenta"\s*:\s*"([^"]+)"/',
            'rent_price' => '/"precioAlquiler"\s*:\s*"([^"]+)"/',
            'neighborhood' => '/"barrio"\s*:\s*"([^"]+)"/',
            'city' => '/"ciudad"\s*:\s*"([^"]+)"/',
            'state' => '/"provincia"\s*:\s*"([^"]+)"/',
            'bedrooms' => '/"ambientes"\s*:\s*"?(\d+)"?/',
            'bathrooms' => '/"banos"\s*:\s*"?(\d+)"?/',
            'area' => '/"superficieTotal"\s*:\s*"?(\d+)"?/',
            'publisher_type' => '/"tipoDePropietario"\s*:\s*"([^"]+)"/',
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
            'departamento' => 'apartment',
            'terreno' => 'land',
            'local' => 'commercial',
            'oficina' => 'office',
            'bodega' => 'warehouse',
            'edificio' => 'building',
            'hotel' => 'hotel',
            'rancho' => 'ranch',
            'nave' => 'industrial',
            'estacionamiento' => 'parking',
            'casa en condominio' => 'house',
            'departamento compartido' => 'apartment',
            'local en centro comercial' => 'commercial',
            'cuarto' => 'room',
            'habitación' => 'room',
            'terreno comercial' => 'land',
            'nave industrial' => 'industrial',
            'consultorio' => 'office',
            'villa' => 'house',
            'bodega comercial' => 'warehouse',
            'penthouse' => 'apartment',
            'loft' => 'apartment',
        ];
    }

    /**
     * Operation type mappings (platform ID => standard type).
     *
     * @return array<int, string>
     */
    public function operationTypes(): array
    {
        return [
            1 => 'sale',
            2 => 'rent',
        ];
    }

    /**
     * Currency type mappings (platform ID => ISO code).
     *
     * @return array<int, string>
     */
    public function currencyTypes(): array
    {
        return [
            1 => 'USD',
            10 => 'MXN',
        ];
    }

    /**
     * Property type mappings (platform ID => standard type).
     *
     * @return array<int, string>
     */
    public function propertyTypes(): array
    {
        return [
            1 => 'house',
            2 => 'apartment',
            3 => 'land',
            4 => 'commercial',
            5 => 'office',
            6 => 'warehouse',
            7 => 'building',
            8 => 'hotel',
            9 => 'ranch',
            10 => 'industrial',
            11 => 'parking',
            12 => 'house',           // casa en condominio
            13 => 'apartment',       // departamento compartido
            14 => 'commercial',      // local en centro comercial
            15 => 'room',            // cuarto/habitación
            16 => 'land',            // terreno comercial
            17 => 'industrial',      // nave industrial
            18 => 'office',          // consultorio
            19 => 'house',           // villa
            20 => 'warehouse',       // bodega comercial
        ];
    }

    /**
     * Publisher type mappings (platform ID => standard type).
     *
     * @return array<int, string>
     */
    public function publisherTypes(): array
    {
        return [
            1 => 'individual',
            2 => 'agency',
            3 => 'developer',
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
            'sala de juntas' => 'meeting_room',
            'salón de eventos' => 'event_room',
            'business center' => 'business_center',
            'usos múltiples' => 'multipurpose_room',
            'juegos' => 'playground',
            'ludoteca' => 'playground',
            'biblioteca' => 'library',
            'cancha' => 'sports_court',
            'tenis' => 'sports_court',
            'padel' => 'sports_court',
            'lobby' => 'lobby',
            'cctv' => 'cctv',
            'cámaras' => 'cctv',
            'pista para correr' => 'jogging_track',
            'jogging' => 'jogging_track',
            'coworking' => 'coworking',
            'spa' => 'spa',
            'vapor' => 'spa',
            'sauna' => 'spa',
            'cine' => 'cinema',
            'cocina integral' => 'integrated_kitchen',
            'área de lavado' => 'laundry_area',
            'balcón' => 'balcony',
            'balcon' => 'balcony',
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
     * CSS selector to wait for on search pages before extracting data.
     */
    public function searchWaitFor(): string
    {
        return '[data-qa^="posting"]';
    }

    /**
     * CSS selector to wait for on listing pages before extracting data.
     */
    public function listingWaitFor(): string
    {
        return '#longDescription, [class*="description"]';
    }
}
