<?php

namespace App\Services\Scrapers;

use App\Contracts\ListingParserInterface;
use App\Contracts\ScraperConfigInterface;

class MercadoLibreListingParser implements ListingParserInterface
{
    /**
     * Mexico coordinate bounds for validation.
     */
    protected const MEXICO_LAT_MIN = 14;

    protected const MEXICO_LAT_MAX = 33;

    protected const MEXICO_LNG_MIN = -118;

    protected const MEXICO_LNG_MAX = -86;

    public function __construct(protected ScraperConfigInterface $config) {}

    /**
     * Parse listing data from ZenRows CSS-extracted data + raw HTML.
     *
     * @param  array<string, mixed>  $extracted  Data from ZenRows css_extractor
     * @param  string  $rawHtml  Raw HTML for JavaScript variable extraction
     * @param  string  $url  Original listing URL
     * @return array<string, mixed>
     */
    public function parse(array $extracted, string $rawHtml, string $url): array
    {
        // Extract JSON-LD structured data (Product + BreadcrumbList)
        $jsonLdData = $this->extractJsonLd($rawHtml);

        // Parse features from HTML tables (MercadoLibre's rich feature tables)
        $features = $this->extractFeatures($jsonLdData, $extracted, $rawHtml);

        // Parse description
        $description = $this->extractDescription($jsonLdData, $extracted, $rawHtml);

        // Build operations array (price/rent info)
        $operations = $this->buildOperations($jsonLdData, $extracted, $url);

        // Parse location from JSON-LD BreadcrumbList
        $location = $this->parseLocation($jsonLdData, $extracted, $rawHtml);

        // Get coordinates from HTML scripts (MercadoLibre embeds lat/lng in page)
        $coordinates = $this->extractCoordinates($rawHtml);

        // Get images
        $images = $this->extractImages($extracted, $rawHtml);

        // Get amenities from feature tables
        $amenities = $this->extractAmenities($extracted, $rawHtml);

        // Determine property type
        $propertyType = $this->resolvePropertyType($jsonLdData, $extracted, $url);

        // Build the final data structure
        return [
            'external_id' => $this->extractExternalId($url, $rawHtml, $jsonLdData),
            'original_url' => $url,
            'title' => $this->extractTitle($extracted, $jsonLdData),
            'description' => $description,

            // Operations (sale/rent with prices)
            'operations' => $operations,

            // Features
            'bedrooms' => $features['bedrooms'],
            'bathrooms' => $features['bathrooms'],
            'half_bathrooms' => $features['half_bathrooms'],
            'parking_spots' => $features['parking_spots'],
            'lot_size_m2' => $features['lot_size_m2'],
            'built_size_m2' => $features['built_size_m2'],
            'age_years' => $features['age_years'],
            'property_type' => $propertyType,
            'property_subtype' => $this->detectSubtype($description ?? '', $extracted['title'] ?? ''),

            // Location
            'address' => $location['address'],
            'colonia' => $location['colonia'],
            'city' => $location['city'],
            'state' => $location['state'],
            'postal_code' => $location['postal_code'],
            'location_raw' => $location['location_raw'],
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],

            // Publisher
            'publisher_name' => $this->extractPublisherName($extracted, $rawHtml),
            'publisher_type' => null,

            // Images
            'images' => $images,

            // Amenities
            'amenities' => $amenities,

            // External codes (MercadoLibre property codes like EB-VB8539)
            'external_codes' => $this->extractExternalCodes($rawHtml, $extracted),

            // Data quality indicators
            'data_quality' => [
                'has_conflicts' => false,
                'confirmed' => [],
                'conflicts' => [],
            ],

            // Platform metadata
            'platform_metadata' => [
                'json_ld_types' => $this->getJsonLdTypes($jsonLdData),
                'sku' => $this->extractSku($jsonLdData, $rawHtml),
            ],
        ];
    }

    /**
     * Extract JSON-LD structured data from HTML.
     * MercadoLibre uses Product and BreadcrumbList types.
     *
     * @return array<array<string, mixed>>
     */
    protected function extractJsonLd(string $html): array
    {
        $data = [];

        // Match all JSON-LD script tags
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.+?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $jsonText) {
                $decoded = json_decode(trim($jsonText), true);
                if ($decoded !== null) {
                    // Handle @graph structure
                    if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                        foreach ($decoded['@graph'] as $item) {
                            if (is_array($item)) {
                                $data[] = $item;
                            }
                        }
                    } else {
                        $data[] = $decoded;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Extract features from HTML tables and JSON-LD.
     * MercadoLibre has extensive feature tables (Principales, Seguridad, etc.)
     *
     * @return array<string, int|float|null>
     */
    protected function extractFeatures(array $jsonLdData, array $extracted, string $rawHtml): array
    {
        $features = [
            'bedrooms' => null,
            'bathrooms' => null,
            'half_bathrooms' => null,
            'parking_spots' => null,
            'lot_size_m2' => null,
            'built_size_m2' => null,
            'age_years' => null,
        ];

        // Extract from HTML tables - format: <th>Label</th><td>Value</td>
        // Bedrooms (Recámaras)
        if (preg_match('/<th[^>]*>Rec[áa]maras<\/th>\s*<td[^>]*>[^<]*?(\d+)/iu', $rawHtml, $match)) {
            $features['bedrooms'] = (int) $match[1];
        }

        // Bathrooms (Baños)
        if (preg_match('/<th[^>]*>Ba[ñn]os?<\/th>\s*<td[^>]*>[^<]*?(\d+(?:\.\d+)?)/iu', $rawHtml, $match)) {
            $features['bathrooms'] = (float) $match[1];
        }

        // Half bathrooms (Medio baño)
        if (preg_match('/<th[^>]*>Medio\s*ba[ñn]o<\/th>\s*<td[^>]*>[^<]*?S[ií]/iu', $rawHtml)) {
            $features['half_bathrooms'] = 1;
        } elseif (preg_match('/<th[^>]*>Medio\s*ba[ñn]o<\/th>\s*<td[^>]*>[^<]*?(\d+)/iu', $rawHtml, $match)) {
            $features['half_bathrooms'] = (int) $match[1];
        }

        // Parking (Estacionamientos)
        if (preg_match('/<th[^>]*>(?:Lugares?\s+de\s+)?[Ee]stacionamientos?<\/th>\s*<td[^>]*>[^<]*?(\d+)/iu', $rawHtml, $match)) {
            $features['parking_spots'] = (int) $match[1];
        }

        // Surface total / lot size
        if (preg_match('/<th[^>]*>Superficie\s+total<\/th>\s*<td[^>]*>[^<]*?(\d+(?:\.\d+)?)\s*m/iu', $rawHtml, $match)) {
            $features['lot_size_m2'] = (float) $match[1];
        }

        // Built surface / constructed area
        if (preg_match('/<th[^>]*>Superficie\s+construida<\/th>\s*<td[^>]*>[^<]*?(\d+(?:\.\d+)?)\s*m/iu', $rawHtml, $match)) {
            $features['built_size_m2'] = (float) $match[1];
        }

        // Age (Antigüedad)
        if (preg_match('/<th[^>]*>Antig[üu]edad<\/th>\s*<td[^>]*>[^<]*?(\d+)\s*a[ñn]os?/iu', $rawHtml, $match)) {
            $features['age_years'] = (int) $match[1];
        }

        // Fallback: inline format from CSS extraction - "Recámaras5" (no separator)
        // ZenRows CSS extraction returns concatenated text like "Recámaras5Baños4..."
        if ($features['bedrooms'] === null && preg_match('/Rec[áa]maras[:\s]*(\d+)/iu', $rawHtml, $match)) {
            $features['bedrooms'] = (int) $match[1];
        }

        if ($features['bathrooms'] === null && preg_match('/Ba[ñn]os[:\s]*(\d+(?:\.\d+)?)/iu', $rawHtml, $match)) {
            $features['bathrooms'] = (float) $match[1];
        }

        if ($features['half_bathrooms'] === null) {
            // Check for "Medio bañoSí" pattern first
            if (preg_match('/Medio\s*ba[ñn]o[:\s]*S[ií]/iu', $rawHtml)) {
                $features['half_bathrooms'] = 1;
            } elseif (preg_match('/Medio\s*ba[ñn]o[:\s]*(\d+)/iu', $rawHtml, $match)) {
                $features['half_bathrooms'] = (int) $match[1];
            }
        }

        if ($features['parking_spots'] === null && preg_match('/(?:Lugares\s+de\s+)?[Ee]stacionamientos?[:\s]*(\d+)/iu', $rawHtml, $match)) {
            $features['parking_spots'] = (int) $match[1];
        }

        if ($features['lot_size_m2'] === null && preg_match('/[Ss]uperficie\s*total[:\s]*(\d+(?:[.,]\d+)?)\s*m/iu', $rawHtml, $match)) {
            $features['lot_size_m2'] = (float) str_replace(',', '.', $match[1]);
        }

        if ($features['built_size_m2'] === null && preg_match('/[Ss]uperficie\s*construida[:\s]*(\d+(?:[.,]\d+)?)\s*m/iu', $rawHtml, $match)) {
            $features['built_size_m2'] = (float) str_replace(',', '.', $match[1]);
        }

        if ($features['age_years'] === null && preg_match('/Antig[üu]edad[:\s]*(\d+)\s*a[ñn]os?/iu', $rawHtml, $match)) {
            $features['age_years'] = (int) $match[1];
        }

        // Additional fallback: extract from features_table CSS extraction
        $featuresTable = $this->toArray($extracted['features_table'] ?? []);
        $featuresText = implode(' ', $featuresTable);

        if ($features['bedrooms'] === null && preg_match('/Rec[áa]maras[:\s]*(\d+)/iu', $featuresText, $match)) {
            $features['bedrooms'] = (int) $match[1];
        }

        if ($features['bathrooms'] === null && preg_match('/Ba[ñn]os[:\s]*(\d+(?:\.\d+)?)/iu', $featuresText, $match)) {
            $features['bathrooms'] = (float) $match[1];
        }

        if ($features['half_bathrooms'] === null) {
            if (preg_match('/Medio\s*ba[ñn]o[:\s]*S[ií]/iu', $featuresText)) {
                $features['half_bathrooms'] = 1;
            }
        }

        if ($features['parking_spots'] === null && preg_match('/(?:Lugares\s+de\s+)?[Ee]stacionamientos?[:\s]*(\d+)/iu', $featuresText, $match)) {
            $features['parking_spots'] = (int) $match[1];
        }

        if ($features['lot_size_m2'] === null && preg_match('/[Ss]uperficie\s*total[:\s]*(\d+(?:[.,]\d+)?)\s*m/iu', $featuresText, $match)) {
            $features['lot_size_m2'] = (float) str_replace(',', '.', $match[1]);
        }

        if ($features['built_size_m2'] === null && preg_match('/[Ss]uperficie\s*construida[:\s]*(\d+(?:[.,]\d+)?)\s*m/iu', $featuresText, $match)) {
            $features['built_size_m2'] = (float) str_replace(',', '.', $match[1]);
        }

        if ($features['age_years'] === null && preg_match('/Antig[üu]edad[:\s]*(\d+)\s*a[ñn]os?/iu', $featuresText, $match)) {
            $features['age_years'] = (int) $match[1];
        }

        // Fallback to patterns in JSON
        if ($features['bedrooms'] === null && preg_match('/"bedrooms?":\s*(\d+)/i', $rawHtml, $match)) {
            $features['bedrooms'] = (int) $match[1];
        }

        if ($features['bathrooms'] === null && preg_match('/"bathrooms?":\s*(\d+(?:\.\d+)?)/i', $rawHtml, $match)) {
            $features['bathrooms'] = (float) $match[1];
        }

        return $features;
    }

    /**
     * Extract description from various sources.
     */
    protected function extractDescription(array $jsonLdData, array $extracted, string $rawHtml): ?string
    {
        // Priority 1: JSON-LD description (Product.description)
        foreach ($jsonLdData as $item) {
            if (isset($item['description']) && ($item['@type'] ?? '') === 'Product') {
                return $this->cleanText($item['description']);
            }
        }

        // Priority 2: CSS extraction
        $description = $extracted['description'] ?? null;
        if (is_array($description)) {
            $description = implode("\n", array_filter($description));
        }

        if (! empty($description)) {
            return $this->cleanText($description);
        }

        // Priority 3: meta description
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $rawHtml, $match)) {
            return $this->cleanText($match[1]);
        }

        return null;
    }

    /**
     * Build operations array from JSON-LD and URL.
     *
     * @return array<array{type: string, price: int|null, currency: string, maintenance_fee: int|null}>
     */
    protected function buildOperations(array $jsonLdData, array $extracted, string $url = ''): array
    {
        $operations = [];

        // Detect operation type from URL
        $urlBasedType = null;
        if (str_contains($url, '/renta') || str_contains($url, '/rent') || str_contains($url, 'alquiler')) {
            $urlBasedType = 'rent';
        } elseif (str_contains($url, '/venta') || str_contains($url, '/sale')) {
            $urlBasedType = 'sale';
        }

        // Priority 1: JSON-LD offers (Product.offers)
        foreach ($jsonLdData as $item) {
            if (($item['@type'] ?? '') === 'Product' && isset($item['offers'])) {
                $offers = is_array($item['offers']) && isset($item['offers'][0])
                    ? $item['offers']
                    : [$item['offers']];

                foreach ($offers as $offer) {
                    $price = $offer['price'] ?? null;
                    if ($price !== null) {
                        $type = $urlBasedType ?? 'rent';

                        $operations[] = [
                            'type' => $type,
                            'price' => (int) $price,
                            'currency' => $offer['priceCurrency'] ?? 'MXN',
                            'maintenance_fee' => null,
                        ];
                    }
                }
            }
        }

        // Priority 2: Extract from CSS price element
        if (empty($operations)) {
            $priceText = $extracted['price'] ?? null;
            if (is_array($priceText)) {
                $priceText = $priceText[0] ?? null;
            }

            if ($priceText && preg_match('/\$\s*([\d,]+)/', $priceText, $match)) {
                $price = (int) str_replace(',', '', $match[1]);
                $currency = str_contains($priceText, 'USD') ? 'USD' : 'MXN';
                $type = $urlBasedType ?? 'rent';

                $operations[] = [
                    'type' => $type,
                    'price' => $price,
                    'currency' => $currency,
                    'maintenance_fee' => null,
                ];
            }
        }

        // Deduplicate by price
        $seen = [];
        $unique = [];
        foreach ($operations as $op) {
            $key = (string) $op['price'];
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $op;
            }
        }

        return $unique;
    }

    /**
     * Parse location from JSON-LD BreadcrumbList and HTML.
     *
     * @return array<string, string|null>
     */
    protected function parseLocation(array $jsonLdData, array $extracted, string $rawHtml): array
    {
        $location = [
            'address' => null,
            'colonia' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'location_raw' => null,
        ];

        // Priority 1: JSON-LD BreadcrumbList
        // MercadoLibre format: [Inmuebles, Casas, Renta, Jalisco, Zapopan, Valle Real]
        foreach ($jsonLdData as $item) {
            if (($item['@type'] ?? '') === 'BreadcrumbList' && isset($item['itemListElement'])) {
                $breadcrumbItems = $item['itemListElement'];
                // Sort by position
                usort($breadcrumbItems, fn ($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

                // Extract names - MercadoLibre uses item.name structure
                $names = [];
                foreach ($breadcrumbItems as $crumb) {
                    $name = $crumb['name'] ?? $crumb['item']['name'] ?? null;
                    if ($name) {
                        $names[] = $name;
                    }
                }

                // Find state, city, colonia from the end (after filtering operation types)
                // Typical: [Inmuebles, Casas, Renta, Jalisco, Zapopan, Valle Real]
                $locationNames = array_values(array_filter($names, function ($name) {
                    return ! in_array(strtolower($name), ['inmuebles', 'casas', 'departamentos', 'terrenos', 'renta', 'venta', 'alquiler']);
                }));

                if (count($locationNames) >= 3) {
                    $location['state'] = $locationNames[0] ?? null;
                    $location['city'] = $locationNames[1] ?? null;
                    $location['colonia'] = $locationNames[2] ?? null;
                } elseif (count($locationNames) >= 2) {
                    $location['state'] = $locationNames[0] ?? null;
                    $location['city'] = $locationNames[1] ?? null;
                }
                break;
            }
        }

        // Priority 2: CSS extraction breadcrumbs
        if (empty($location['state']) || empty($location['city'])) {
            $breadcrumbs = $this->toArray($extracted['breadcrumbs'] ?? []);
            $cleanBreadcrumbs = array_values(array_filter($breadcrumbs, function ($b) {
                $b = trim($b);

                return ! empty($b)
                    && ! in_array(strtolower($b), ['inmuebles', 'casas', 'departamentos', 'renta', 'venta', 'inicio']);
            }));

            if (count($cleanBreadcrumbs) >= 2) {
                if (empty($location['state']) && isset($cleanBreadcrumbs[0])) {
                    $location['state'] = $this->cleanText($cleanBreadcrumbs[0]);
                }
                if (empty($location['city']) && isset($cleanBreadcrumbs[1])) {
                    $location['city'] = $this->cleanText($cleanBreadcrumbs[1]);
                }
                if (empty($location['colonia']) && isset($cleanBreadcrumbs[2])) {
                    $location['colonia'] = $this->cleanText($cleanBreadcrumbs[2]);
                }
            }
        }

        // Build raw location string
        $locationParts = array_filter([
            $location['address'],
            $location['colonia'],
            $location['city'],
            $location['state'],
        ]);
        if (! empty($locationParts)) {
            $location['location_raw'] = implode(', ', $locationParts);
        }

        return $location;
    }

    /**
     * Extract coordinates from HTML scripts.
     * MercadoLibre embeds latitude/longitude in JavaScript.
     *
     * @return array{latitude: float|null, longitude: float|null}
     */
    protected function extractCoordinates(string $rawHtml): array
    {
        $lat = null;
        $lng = null;

        // MercadoLibre coordinate patterns
        $patterns = $this->config->jsPatterns();

        if (preg_match($patterns['latitude'], $rawHtml, $match)) {
            $lat = (float) $match[1];
        }

        if (preg_match($patterns['longitude'], $rawHtml, $match)) {
            $lng = (float) $match[1];
        }

        // Validate coordinates are within Mexico
        if ($lat !== null && $lng !== null) {
            if (! $this->isValidMexicoCoordinate($lat, $lng)) {
                $lat = null;
                $lng = null;
            }
        }

        return [
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    /**
     * Check if coordinates are within Mexico bounds.
     */
    protected function isValidMexicoCoordinate(float $lat, float $lng): bool
    {
        return $lat >= self::MEXICO_LAT_MIN
            && $lat <= self::MEXICO_LAT_MAX
            && $lng >= self::MEXICO_LNG_MIN
            && $lng <= self::MEXICO_LNG_MAX;
    }

    /**
     * Extract images from HTML and CSS extraction.
     *
     * @return array<string>
     */
    protected function extractImages(array $extracted, string $rawHtml): array
    {
        $images = [];
        $seen = [];

        // From CSS extraction
        $galleryImages = $this->toArray($extracted['gallery_images'] ?? []);
        foreach ($galleryImages as $url) {
            if (! empty($url) && ! isset($seen[$url])) {
                $seen[$url] = true;
                $images[] = $url;
            }
        }

        // Extract from HTML - MercadoLibre CDN images (http2.mlstatic.com)
        if (preg_match_all('/https?:\/\/http2\.mlstatic\.com[^"\'>\s]*\.(?:jpg|jpeg|png|webp)/i', $rawHtml, $matches)) {
            foreach ($matches[0] as $url) {
                if (! isset($seen[$url])) {
                    $seen[$url] = true;
                    $images[] = $url;
                }
            }
        }

        // Extract from JSON-LD image property
        if (preg_match_all('/"image"\s*:\s*"([^"]+)"/i', $rawHtml, $matches)) {
            foreach ($matches[1] as $url) {
                if (! isset($seen[$url]) && str_contains($url, 'http')) {
                    $seen[$url] = true;
                    $images[] = $url;
                }
            }
        }

        return $images;
    }

    /**
     * Extract amenities from HTML feature tables.
     * MercadoLibre has: Ambientes, Seguridad, Comodidades, Servicios sections.
     *
     * @return array<string>
     */
    protected function extractAmenities(array $extracted, string $rawHtml): array
    {
        $amenities = [];

        // Extract from feature tables - look for "Sí" values
        // Format: <th>Alberca</th><td>Sí</td>
        if (preg_match_all('/<t[hd][^>]*>([^<]+)<\/t[hd]>\s*<td[^>]*>\s*S[ií]\s*<\/td>/iu', $rawHtml, $matches)) {
            foreach ($matches[1] as $feature) {
                $amenities[] = trim($feature);
            }
        }

        // Also extract from list items that are checked
        if (preg_match_all('/<li[^>]*class="[^"]*checked[^"]*"[^>]*>([^<]+)</i', $rawHtml, $matches)) {
            foreach ($matches[1] as $feature) {
                $amenities[] = trim($feature);
            }
        }

        // Extract from CSS extraction features table
        // ZenRows format: "TerrazaSíJardínSíAlbercaNo..."
        $featuresTable = $this->toArray($extracted['features_table'] ?? []);
        foreach ($featuresTable as $row) {
            if (is_string($row)) {
                // Match pattern: FeatureNameSí (feature followed by Sí)
                if (preg_match_all('/([A-Za-záéíóúñÁÉÍÓÚÑ\s]+)S[ií]/u', $row, $matches)) {
                    foreach ($matches[1] as $feature) {
                        $feature = trim($feature);
                        if (! empty($feature) && mb_strlen($feature) > 1) {
                            $amenities[] = $feature;
                        }
                    }
                }
            }
        }

        // Standardize amenities
        return $this->standardizeAmenities($amenities);
    }

    /**
     * Standardize amenity names using config mappings.
     *
     * @param  array<string>  $amenities
     * @return array<string>
     */
    protected function standardizeAmenities(array $amenities): array
    {
        $mappings = $this->config->amenityMappings();
        $standardized = [];

        foreach ($amenities as $amenity) {
            $normalized = mb_strtolower(trim($amenity));

            // Direct mapping
            if (isset($mappings[$normalized])) {
                $standardized[] = $mappings[$normalized];

                continue;
            }

            // Partial match
            foreach ($mappings as $keyword => $standard) {
                if (str_contains($normalized, $keyword)) {
                    $standardized[] = $standard;
                    break;
                }
            }
        }

        return array_values(array_unique($standardized));
    }

    /**
     * Resolve property type from various sources.
     */
    protected function resolvePropertyType(array $jsonLdData, array $extracted, string $url): string
    {
        // Priority 1: From URL (MercadoLibre has clear URL structure)
        // Format: https://casa.mercadolibre.com.mx/... or departamento.mercadolibre.com.mx
        if (preg_match('/(casa|departamento|terreno|local|oficina|bodega)\.mercadolibre/i', $url, $match)) {
            $mappings = $this->config->propertyTypes();

            return $mappings[strtolower($match[1])] ?? 'house';
        }

        // Priority 2: From URL path
        if (preg_match('/\/(casas?|departamentos?|terrenos?|locales?|oficinas?|bodegas?)[-\/]/i', $url, $match)) {
            $mappings = $this->config->propertyTypes();
            $type = strtolower(rtrim($match[1], 's'));

            return $mappings[$type] ?? 'house';
        }

        // Priority 3: JSON-LD BreadcrumbList
        foreach ($jsonLdData as $item) {
            if (($item['@type'] ?? '') === 'BreadcrumbList' && isset($item['itemListElement'])) {
                foreach ($item['itemListElement'] as $crumb) {
                    $name = strtolower($crumb['name'] ?? '');
                    $mappings = $this->config->propertyTypes();
                    if (isset($mappings[$name])) {
                        return $mappings[$name];
                    }
                }
            }
        }

        // Priority 4: From title
        $title = $extracted['title'] ?? '';
        if (is_array($title)) {
            $title = $title[0] ?? '';
        }

        if (preg_match('/(Casa|Departamento|Terreno|Local|Oficina|Bodega)/iu', $title, $match)) {
            $mappings = $this->config->propertyTypes();

            return $mappings[mb_strtolower($match[1])] ?? 'house';
        }

        return 'house';
    }

    /**
     * Detect property subtype from description/title.
     */
    protected function detectSubtype(?string $description, ?string $title): ?string
    {
        $text = strtoupper(($description ?? '').($title ?? ''));

        $patterns = $this->config->subtypePatterns();
        foreach ($patterns as $pattern => $subtype) {
            if (preg_match($pattern, $text)) {
                return $subtype;
            }
        }

        return null;
    }

    /**
     * Extract external ID from URL, HTML, or JSON-LD.
     */
    protected function extractExternalId(string $url, string $rawHtml, array $jsonLdData): ?string
    {
        // From URL - MercadoLibre uses MLM-{number} format
        $id = $this->config->extractExternalId($url);
        if ($id !== null) {
            return $id;
        }

        // From JSON-LD Product sku
        foreach ($jsonLdData as $item) {
            if (($item['@type'] ?? '') === 'Product' && isset($item['sku'])) {
                return $item['sku'];
            }
        }

        // From HTML patterns
        $patterns = $this->config->jsPatterns();
        if (isset($patterns['sku']) && preg_match($patterns['sku'], $rawHtml, $match)) {
            return $match[1];
        }

        if (isset($patterns['productID']) && preg_match($patterns['productID'], $rawHtml, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * Extract SKU from JSON-LD or HTML.
     */
    protected function extractSku(array $jsonLdData, string $rawHtml): ?string
    {
        foreach ($jsonLdData as $item) {
            if (isset($item['sku'])) {
                return $item['sku'];
            }
        }

        $patterns = $this->config->jsPatterns();
        if (isset($patterns['sku']) && preg_match($patterns['sku'], $rawHtml, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * Extract external codes (property codes like EB-VB8539).
     *
     * @return array<string, string>
     */
    protected function extractExternalCodes(string $rawHtml, array $extracted): array
    {
        $codes = [];

        // MercadoLibre property codes - typically from agencies
        // Format: EB-VB8539, CAV-1234, etc.
        if (preg_match('/(?:C[óo]digo|Referencia|ID)[:\s]+([A-Z]{2,4}-[A-Z0-9]{3,10})/iu', $rawHtml, $match)) {
            $codes['property_code'] = strtoupper($match[1]);
        }

        // From CSS extraction
        $propertyCode = $extracted['property_code'] ?? null;
        if (is_array($propertyCode)) {
            $propertyCode = $propertyCode[0] ?? null;
        }
        if (! empty($propertyCode)) {
            $codes['property_code'] = $this->cleanText($propertyCode);
        }

        return $codes;
    }

    /**
     * Extract title from CSS extraction or JSON-LD.
     */
    protected function extractTitle(array $extracted, array $jsonLdData): ?string
    {
        // Priority 1: CSS extraction
        $title = $extracted['title'] ?? null;
        if (is_array($title)) {
            $title = $title[0] ?? null;
        }

        if (! empty($title)) {
            return $this->cleanText($title);
        }

        // Priority 2: JSON-LD Product name
        foreach ($jsonLdData as $item) {
            if (($item['@type'] ?? '') === 'Product' && ! empty($item['name'])) {
                return $this->cleanText($item['name']);
            }
        }

        return null;
    }

    /**
     * Extract publisher name from CSS extraction or HTML.
     */
    protected function extractPublisherName(array $extracted, string $rawHtml): ?string
    {
        // Priority 1: CSS extraction
        $publisherName = $extracted['publisher_name'] ?? null;
        if (is_array($publisherName)) {
            $publisherName = $publisherName[0] ?? null;
        }

        if (! empty($publisherName)) {
            return $this->cleanText($publisherName);
        }

        // Priority 2: Extract from seller section in HTML
        if (preg_match('/<a[^>]+class="[^"]*seller[^"]*"[^>]*>([^<]+)</i', $rawHtml, $match)) {
            return $this->cleanText($match[1]);
        }

        // Priority 3: Look for agent/agency patterns
        if (preg_match('/(?:Publicado por|Vendedor|Agente)[:\s]+([^<\n]+)/iu', $rawHtml, $match)) {
            return $this->cleanText($match[1]);
        }

        return null;
    }

    /**
     * Get JSON-LD types for metadata.
     *
     * @return array<string>
     */
    protected function getJsonLdTypes(array $jsonLdData): array
    {
        $types = [];
        foreach ($jsonLdData as $item) {
            if (isset($item['@type'])) {
                $types[] = $item['@type'];
            }
        }

        return array_unique($types);
    }

    /**
     * Ensure value is an array.
     *
     * @return array<mixed>
     */
    protected function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return [$value];
        }

        return [];
    }

    /**
     * Clean text by normalizing whitespace.
     */
    protected function cleanText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text ?: null;
    }
}
