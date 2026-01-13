<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperConfigInterface;

class VivanunciosConfig implements ScraperConfigInterface
{
    /**
     * CSS extractor configuration for search pages.
     *
     * @return array<string, string>
     */
    public function searchExtractor(): array
    {
        return [
            // Listing URLs - use data-to-posting attribute
            'urls' => '[data-qa="posting PROPERTY"] @data-to-posting',

            // External IDs from data-id attribute
            'external_ids' => '[data-qa="posting PROPERTY"] @data-id',

            // Preview data (shown during discovery)
            'titles' => '[data-qa="POSTING_CARD_DESCRIPTION"]',
            'prices' => '[data-qa="POSTING_CARD_PRICE"]',
            'locations' => '[data-qa="POSTING_CARD_LOCATION"]',
            'images' => '[data-qa="POSTING_CARD_GALLERY"] img @src',

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
            // JSON-LD structured data (most reliable)
            'json_ld' => 'script[type="application/ld+json"]',

            // Core info
            'title' => 'article h1',
            'description' => 'article [class*="description"], #description',

            // Features (icon-based elements - similar to Inmuebles24)
            'bedrooms_text' => '.icon-dormitorio, [class*="bed"]',
            'bathrooms_text' => '.icon-bano, [class*="bath"]',
            'parking_text' => '.icon-cochera, [class*="parking"]',
            'area_text' => '[class*="totalArea"], [class*="area"]',

            // Feature items
            'feature_items' => 'article ul li',

            // Location
            'location_header' => 'article h4, [class*="address"]',
            'breadcrumbs' => '[class*="breadcrumb"] a',

            // Images
            'gallery_images' => '[class*="gallery"] img @src, [class*="carousel"] img @src',

            // Publisher info
            'publisher_name' => '[class*="seller"] a, a[href*="u-anuncios-del-vendedor"]',
            'phone_link' => 'a[href*="tel:"] @href',

            // External codes
            'vivanuncios_code' => '[class*="code"], [class*="id"]',

            // Price containers
            'price_containers' => '[class*="price"]',
        ];
    }

    /**
     * Regex patterns for extracting data from JavaScript variables.
     * Vivanuncios uses similar patterns to Inmuebles24 (same parent company).
     *
     * @return array<string, string>
     */
    public function jsPatterns(): array
    {
        return [
            'price' => "/'price':\s*'(\d+)'/",
            'currency_id' => "/'currencyId':\s*'(\d+)'/",
            'operation_type_id' => "/'operationTypeId':\s*'(\d+)'/",
            'property_type_id' => "/'propertyTypeId':\s*'(\d+)'/",
            'posting_id' => "/'postingId':\s*'(\d+)'/",
            'publisher_id' => "/'publisherId':\s*'(\d+)'/",
            'latitude' => '/"latitude":\s*([-\d.]+)/',
            'longitude' => '/"longitude":\s*([-\d.]+)/',
        ];
    }

    /**
     * URL pattern for extracting external ID.
     * Vivanuncios uses numeric IDs at the end of URLs.
     */
    public function externalIdPattern(): string
    {
        return '/(\d{6,})$/';
    }

    /**
     * Generate paginated URL from base URL and page number.
     * Vivanuncios uses p1, p2, p3 format at the end of URLs.
     */
    public function paginateUrl(string $baseUrl, int $page): string
    {
        // Vivanuncios uses pN format at end of URL
        // Example: /s-renta-inmuebles/tonala-jalisco/v1c1098l16498p1
        if (preg_match('/p\d+$/', $baseUrl)) {
            return preg_replace('/p\d+$/', "p{$page}", $baseUrl);
        }

        // Add pagination if not present
        return rtrim($baseUrl, '/')."p{$page}";
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
        ];
    }

    /**
     * Map Spanish property type names to standard types.
     * Vivanuncios uses similar property type names to Inmuebles24.
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
            'apartment' => 'apartment',
            'house' => 'house',
            'singlefamilyresidence' => 'house',
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
     * Currency type mappings (platform ID => standard code).
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
     * Same mappings as Inmuebles24 (same parent company).
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
            'seguridad' => 'security_24h',
            'vigilancia' => 'security_24h',
            'elevador' => 'elevator',
            'ascensor' => 'elevator',
            'roof' => 'roof_garden',
            'terraza' => 'terrace',
            'jardín' => 'garden',
            'jardin' => 'garden',
            'áreas verdes' => 'green_areas',
            'pet friendly' => 'pet_friendly',
            'mascotas' => 'pet_friendly',
            'estacionamiento' => 'parking',
            'bodega' => 'storage',
            'aire acondicionado' => 'ac',
            'amueblado' => 'furnished',
        ];
    }
}
