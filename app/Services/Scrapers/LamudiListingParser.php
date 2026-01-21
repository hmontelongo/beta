<?php

namespace App\Services\Scrapers;

use App\Contracts\ListingParserInterface;
use App\Contracts\ScraperConfigInterface;

class LamudiListingParser implements ListingParserInterface
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
        // Extract JSON-LD structured data (primary source for Lamudi)
        $jsonLdData = $this->extractJsonLd($rawHtml);

        // Extract from meta tags (fallback for coordinates)
        $metaData = $this->extractMetaTags($rawHtml);

        // Parse features from JSON-LD and HTML
        $features = $this->extractFeatures($jsonLdData, $extracted, $rawHtml);

        // Parse description
        $description = $this->extractDescription($jsonLdData, $extracted, $rawHtml);

        // Build operations array (price/rent info)
        $operations = $this->buildOperations($jsonLdData, $extracted, $url);

        // Parse location from JSON-LD and breadcrumbs
        $location = $this->parseLocation($jsonLdData, $extracted, $rawHtml);

        // Get coordinates from JSON-LD or meta tags
        $coordinates = $this->extractCoordinates($metaData, $jsonLdData, $rawHtml);

        // Get images from HTML
        $images = $this->extractImages($extracted, $rawHtml);

        // Get amenities from JSON-LD and HTML
        $amenities = $this->extractAmenities($jsonLdData, $extracted, $rawHtml);

        // Determine property type
        $propertyType = $this->resolvePropertyType($jsonLdData, $extracted, $url);

        // Build the final data structure
        return [
            'external_id' => $this->extractExternalId($url, $rawHtml),
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

            // External codes (Lamudi reference codes like CCR-162-67995)
            'external_codes' => $this->extractExternalCodes($rawHtml),

            // Data quality indicators
            'data_quality' => [
                'has_conflicts' => false,
                'confirmed' => [],
                'conflicts' => [],
            ],

            // Platform metadata
            'platform_metadata' => [
                'json_ld_types' => $this->getJsonLdTypes($jsonLdData),
            ],
        ];
    }

    /**
     * Extract JSON-LD structured data from HTML.
     * Handles Lamudi's @graph structure which wraps multiple items.
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
                    // Handle @graph structure (Lamudi wraps items in @graph array)
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
     * Extract meta tags from HTML.
     *
     * @return array<string, string>
     */
    protected function extractMetaTags(string $html): array
    {
        $metas = [];

        // Extract meta tags with name attribute
        if (preg_match_all('/<meta[^>]+name=["\']([^"\']+)["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $metas[$match[1]] = $match[2];
            }
        }

        // Also match content before name
        if (preg_match_all('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $metas[$match[2]] = $match[1];
            }
        }

        return $metas;
    }

    /**
     * Extract features from JSON-LD and HTML.
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

        // Priority 1: JSON-LD (most reliable for Lamudi)
        foreach ($jsonLdData as $item) {
            if ($features['bedrooms'] === null && isset($item['numberOfBedrooms'])) {
                $features['bedrooms'] = (int) $item['numberOfBedrooms'];
            }
            if ($features['bathrooms'] === null && isset($item['numberOfBathroomsTotal'])) {
                $features['bathrooms'] = (float) $item['numberOfBathroomsTotal'];
            }
            if ($features['lot_size_m2'] === null && isset($item['floorSize']['value'])) {
                $features['lot_size_m2'] = (float) $item['floorSize']['value'];
            }
            if ($features['age_years'] === null && isset($item['yearBuilt'])) {
                $currentYear = (int) date('Y');
                $features['age_years'] = $currentYear - (int) $item['yearBuilt'];
            }
            // Lamudi sometimes has numberOfRooms for parking
            if ($features['parking_spots'] === null && isset($item['numberOfRooms'])) {
                // Don't use numberOfRooms as parking - this is total rooms
            }
        }

        // Priority 2: HTML patterns
        if ($features['bedrooms'] === null && preg_match('/(\d+)\s*rec[áa]maras?/iu', $rawHtml, $match)) {
            $features['bedrooms'] = (int) $match[1];
        }

        if ($features['bathrooms'] === null && preg_match('/(\d+(?:\.\d+)?)\s*ba[ñn]os?/iu', $rawHtml, $match)) {
            $features['bathrooms'] = (float) $match[1];
        }

        if ($features['parking_spots'] === null && preg_match('/(\d+)\s*(?:estacionamientos?|cocheras?|lugares?\s*de\s*estacionamiento)/iu', $rawHtml, $match)) {
            $features['parking_spots'] = (int) $match[1];
        }

        if ($features['lot_size_m2'] === null && preg_match('/[áa]rea\s+(?:del\s+)?terreno[:\s]+(\d+)\s*m/iu', $rawHtml, $match)) {
            $features['lot_size_m2'] = (float) $match[1];
        }

        if ($features['built_size_m2'] === null && preg_match('/[áa]rea\s+(?:de\s+)?construcci[óo]n[:\s]+(\d+)\s*m/iu', $rawHtml, $match)) {
            $features['built_size_m2'] = (float) $match[1];
        }

        // Try JSON number patterns in scripts
        if ($features['parking_spots'] === null && preg_match('/"parking"[:\s]+(\d+)/i', $rawHtml, $match)) {
            $features['parking_spots'] = (int) $match[1];
        }

        return $features;
    }

    /**
     * Extract description from various sources.
     */
    protected function extractDescription(array $jsonLdData, array $extracted, string $rawHtml): ?string
    {
        // Priority 1: JSON-LD
        foreach ($jsonLdData as $item) {
            if (! empty($item['description'])) {
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

        // Detect operation type from URL (most reliable for Lamudi)
        $urlBasedType = null;
        if (str_contains($url, '/for-rent') || str_contains($url, '/en-renta')) {
            $urlBasedType = 'rent';
        } elseif (str_contains($url, '/for-sale') || str_contains($url, '/en-venta')) {
            $urlBasedType = 'sale';
        }

        // Priority 1: JSON-LD offers
        foreach ($jsonLdData as $item) {
            if (isset($item['offers'])) {
                $offers = is_array($item['offers']) && isset($item['offers'][0])
                    ? $item['offers']
                    : [$item['offers']];

                foreach ($offers as $offer) {
                    $price = $offer['price'] ?? null;
                    if ($price !== null) {
                        // Determine type from URL or offer
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
     * Parse location from JSON-LD and breadcrumbs.
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

        // Priority 1: JSON-LD address (SingleFamilyResidence, Apartment, etc.)
        foreach ($jsonLdData as $item) {
            if (isset($item['address']) && ($item['@type'] ?? '') !== 'BreadcrumbList') {
                $addr = $item['address'];
                if (is_array($addr)) {
                    $location['address'] = $addr['streetAddress'] ?? null;
                    $location['city'] = $addr['addressLocality'] ?? null;
                    $location['state'] = $addr['addressRegion'] ?? null;
                    $location['postal_code'] = $addr['postalCode'] ?? null;
                } elseif (is_string($addr)) {
                    $location['address'] = $addr;
                }
                break; // Found address, stop looking
            }
        }

        // Extract colonia from address if present (e.g., "Calle X, Granja, Zapopan...")
        if ($location['address'] && preg_match('/,\s*([^,]+),\s*'.preg_quote($location['city'] ?? '', '/').'/iu', $location['address'], $match)) {
            $location['colonia'] = trim($match[1]);
        }

        // Priority 2: JSON-LD BreadcrumbList for city/state/colonia fallback
        if (empty($location['state']) || empty($location['city'])) {
            foreach ($jsonLdData as $item) {
                if (($item['@type'] ?? '') === 'BreadcrumbList' && isset($item['itemListElement'])) {
                    $breadcrumbItems = $item['itemListElement'];
                    // Typical: [Renta, Jalisco, Zapopan, Ciudad Granja]
                    foreach ($breadcrumbItems as $crumb) {
                        $pos = $crumb['position'] ?? 0;
                        $name = $crumb['name'] ?? '';
                        if ($pos === 2 && empty($location['state'])) {
                            $location['state'] = $name;
                        } elseif ($pos === 3 && empty($location['city'])) {
                            $location['city'] = $name;
                        } elseif ($pos === 4 && empty($location['colonia'])) {
                            $location['colonia'] = $name;
                        }
                    }
                    break;
                }
            }
        }

        // Priority 3: CSS extraction location - look for clean "City, State" pattern
        $extractedLocations = $this->toArray($extracted['location'] ?? []);
        foreach ($extractedLocations as $loc) {
            $loc = $this->cleanText($loc);
            // Look for clean "Colonia, City, State" pattern (no "Localización" or "Ver en mapa")
            if ($loc && ! str_contains($loc, 'Localización') && ! str_contains($loc, 'Ver en mapa') && ! str_contains($loc, 'Cerca de')) {
                if (preg_match('/^([^,]+),\s*([^,]+),\s*([^,]+)$/', $loc, $matches)) {
                    if (empty($location['colonia'])) {
                        $location['colonia'] = trim($matches[1]);
                    }
                    if (empty($location['city'])) {
                        $location['city'] = trim($matches[2]);
                    }
                    if (empty($location['state'])) {
                        $location['state'] = trim($matches[3]);
                    }
                    break;
                }
            }
        }

        // Priority 4: CSS breadcrumbs fallback (Lamudi puts nav links before actual breadcrumbs)
        // Use last 4 items which are typically: Renta/Venta, State, City, Colonia
        if (empty($location['state']) || empty($location['city'])) {
            $breadcrumbs = $this->toArray($extracted['breadcrumbs'] ?? []);
            // Filter to only non-empty, non-navigation items
            $cleanBreadcrumbs = array_values(array_filter($breadcrumbs, function ($b) {
                $b = trim($b);

                return ! empty($b)
                    && ! str_contains($b, 'Venta')
                    && ! str_contains($b, 'Renta')
                    && ! str_contains($b, 'Publicar')
                    && ! str_contains($b, 'Entrar')
                    && ! str_contains($b, 'Favoritos')
                    && ! str_contains($b, 'Desarrollos')
                    && ! str_contains($b, 'Remates')
                    && ! str_contains($b, 'Aviso Legal')
                    && ! str_contains($b, 'Privacidad')
                    && ! str_contains($b, 'Todas las');
            }));

            // If we have clean breadcrumbs, use them
            if (count($cleanBreadcrumbs) >= 2) {
                // For tests: simple array like ['Inicio', 'Jalisco', 'Guadalajara', 'Providencia']
                if (empty($location['state']) && isset($cleanBreadcrumbs[1])) {
                    $location['state'] = $this->cleanText($cleanBreadcrumbs[1]);
                }
                if (empty($location['city']) && isset($cleanBreadcrumbs[2])) {
                    $location['city'] = $this->cleanText($cleanBreadcrumbs[2]);
                }
                if (empty($location['colonia']) && isset($cleanBreadcrumbs[3])) {
                    $location['colonia'] = $this->cleanText($cleanBreadcrumbs[3]);
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
     * Extract coordinates from JSON-LD, meta tags, or HTML.
     *
     * @return array{latitude: float|null, longitude: float|null}
     */
    protected function extractCoordinates(array $metaData, array $jsonLdData, string $rawHtml): array
    {
        $lat = null;
        $lng = null;

        // Priority 1: JSON-LD geo property
        foreach ($jsonLdData as $item) {
            if (isset($item['geo']['latitude'], $item['geo']['longitude'])) {
                $lat = (float) $item['geo']['latitude'];
                $lng = (float) $item['geo']['longitude'];
                break;
            }
        }

        // Priority 2: meta tags (ICBM or geo.position)
        if ($lat === null && isset($metaData['ICBM'])) {
            $parts = explode(',', $metaData['ICBM']);
            if (count($parts) === 2) {
                $lat = (float) trim($parts[0]);
                $lng = (float) trim($parts[1]);
            }
        }

        if ($lat === null && isset($metaData['geo.position'])) {
            $parts = explode(';', $metaData['geo.position']);
            if (count($parts) === 2) {
                $lat = (float) trim($parts[0]);
                $lng = (float) trim($parts[1]);
            }
        }

        // Priority 3: regex patterns in HTML/scripts
        if ($lat === null) {
            // Match lat/latitude patterns
            if (preg_match('/["\']?lat(?:itude)?["\']?\s*[:=]\s*["\']?([-\d.]+)/i', $rawHtml, $match)) {
                $lat = (float) $match[1];
            }
            // Match lng/longitude patterns
            if (preg_match('/["\']?l(?:ng|on(?:gitude)?)["\']?\s*[:=]\s*["\']?([-\d.]+)/i', $rawHtml, $match)) {
                $lng = (float) $match[1];
            }
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
     * Extract images from HTML.
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

        // Extract from HTML - Lamudi CDN images
        if (preg_match_all('/https?:\/\/[^"\'>\s]*lamudi[^"\'>\s]*\.(?:jpg|jpeg|png|webp)/i', $rawHtml, $matches)) {
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

        // Extract image arrays from JSON-LD
        if (preg_match_all('/"image"\s*:\s*\[([^\]]+)\]/s', $rawHtml, $matches)) {
            foreach ($matches[1] as $imageArray) {
                if (preg_match_all('/"([^"]+\.(?:jpg|jpeg|png|webp))"/i', $imageArray, $urlMatches)) {
                    foreach ($urlMatches[1] as $url) {
                        if (! isset($seen[$url])) {
                            $seen[$url] = true;
                            $images[] = $url;
                        }
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Extract amenities from JSON-LD and HTML.
     *
     * @return array<string>
     */
    protected function extractAmenities(array $jsonLdData, array $extracted, string $rawHtml): array
    {
        $amenities = [];

        // From JSON-LD amenityFeature
        foreach ($jsonLdData as $item) {
            if (isset($item['amenityFeature']) && is_array($item['amenityFeature'])) {
                foreach ($item['amenityFeature'] as $feature) {
                    if (isset($feature['name'])) {
                        $amenities[] = $feature['name'];
                    } elseif (is_string($feature)) {
                        $amenities[] = $feature;
                    }
                }
            }
        }

        // From HTML - extract amenities section
        if (preg_match('/(?:Amenidades|Caracter[íi]sticas)[^<]*<\/(?:h2|h3)>(.{0,3000}?)(?:<\/(?:section|div)>|<h[23])/si', $rawHtml, $match)) {
            $section = $match[1];
            // Extract individual items
            if (preg_match_all('/<(?:li|span)[^>]*>([^<]{2,50})<\/(?:li|span)>/u', $section, $items)) {
                foreach ($items[1] as $item) {
                    $item = trim(strip_tags($item));
                    if (strlen($item) > 2 && strlen($item) < 50 && ! preg_match('/^\d/', $item)) {
                        $amenities[] = $item;
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
        // Priority 1: JSON-LD @type
        foreach ($jsonLdData as $item) {
            $type = $item['@type'] ?? null;
            if ($type) {
                return match ($type) {
                    'Apartment' => 'apartment',
                    'House', 'SingleFamilyResidence' => 'house',
                    'LandPlot' => 'land',
                    'OfficeBuilding' => 'office',
                    default => $this->mapPropertyType($type),
                };
            }
        }

        // Priority 2: From URL pattern
        if (preg_match('/\/(casa|departamento|terreno|local|oficina|bodega)[-s]?/i', $url, $match)) {
            $mappings = $this->config->propertyTypes();

            return $mappings[strtolower($match[1])] ?? 'house';
        }

        // Priority 3: From title
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
     * Map JSON-LD type to standard type.
     */
    protected function mapPropertyType(string $type): string
    {
        $type = strtolower($type);
        $mappings = $this->config->propertyTypeTextMappings();

        return $mappings[$type] ?? 'house';
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
     * Extract external ID from URL or HTML.
     */
    protected function extractExternalId(string $url, string $rawHtml): ?string
    {
        // From URL - Lamudi uses complex IDs like: 41032-73-6153fc4615a4-92a2-19baec7-ab01-7257
        $id = $this->config->extractExternalId($url);
        if ($id !== null) {
            return $id;
        }

        // From HTML - look for posting ID patterns
        if (preg_match('/"postingId"\s*:\s*"([^"]+)"/i', $rawHtml, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * Extract external codes (reference codes like CCR-162-67995).
     *
     * @return array<string, string>
     */
    protected function extractExternalCodes(string $rawHtml): array
    {
        $codes = [];

        // Lamudi reference codes (CCR-XXX-XXXXX)
        if (preg_match('/CCR-[\d-]+/', $rawHtml, $match)) {
            $codes['reference_code'] = $match[0];
        }

        // General reference/codigo patterns
        if (preg_match('/(?:C[óo]digo|Ref(?:erencia)?|ID)[:\s]+([A-Z0-9-]+)/iu', $rawHtml, $match)) {
            if (strlen($match[1]) >= 5 && strlen($match[1]) <= 30) {
                $codes['listing_code'] = $match[1];
            }
        }

        return $codes;
    }

    /**
     * Extract title from CSS extraction or JSON-LD.
     */
    protected function extractTitle(array $extracted, array $jsonLdData): ?string
    {
        // Priority 1: CSS extraction (usually has full title)
        $title = $extracted['title'] ?? null;
        if (is_array($title)) {
            $title = $title[0] ?? null;
        }

        if (! empty($title)) {
            return $this->cleanText($title);
        }

        // Priority 2: JSON-LD name
        foreach ($jsonLdData as $item) {
            if (! empty($item['name'])) {
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

        // Priority 2: Extract from agent/seller section in HTML
        if (preg_match('/<a[^>]+class="[^"]*seller[^"]*"[^>]*>([^<]+)</i', $rawHtml, $match)) {
            return $this->cleanText($match[1]);
        }

        // Priority 3: JSON-LD seller/agent
        if (preg_match('/"seller"\s*:\s*\{[^}]*"name"\s*:\s*"([^"]+)"/i', $rawHtml, $match)) {
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
