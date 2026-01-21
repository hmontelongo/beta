<?php

namespace App\Services\Scrapers;

use App\Contracts\ListingParserInterface;
use App\Contracts\ScraperConfigInterface;

class PropiedadesListingParser implements ListingParserInterface
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
        // Extract JSON-LD structured data
        $jsonLdData = $this->extractJsonLd($rawHtml);

        // Extract from meta tags (coordinates)
        $metaData = $this->extractMetaTags($rawHtml);

        // Extract from __NEXT_DATA__ (primary data source for propiedades.com)
        $nextData = $this->extractNextData($rawHtml);
        $nextResults = $nextData['props']['pageProps']['results'] ?? [];
        $nextProperty = $nextResults['property'] ?? [];
        $nextAmenities = $nextResults['amenities'] ?? [];
        $nextGallery = $nextResults['gallery'] ?? [];
        $nextSeo = $nextResults['seo'] ?? [];
        $nextProfile = $nextResults['profile'] ?? [];
        $nextServices = $nextResults['services'] ?? [];

        // Parse features from __NEXT_DATA__, JSON-LD and HTML
        $features = $this->extractFeatures($jsonLdData, $extracted, $rawHtml, $nextProperty, $nextAmenities);

        // Parse description (priority: __NEXT_DATA__ > JSON-LD > meta)
        $description = $this->extractDescription($jsonLdData, $extracted, $rawHtml, $nextProperty);

        // Build operations array (price/rent info)
        $operations = $this->buildOperations($jsonLdData, $extracted, $url, $nextProperty);

        // Parse location from __NEXT_DATA__, JSON-LD and breadcrumbs
        $location = $this->parseLocation($jsonLdData, $extracted, $rawHtml, $nextProperty);

        // Get coordinates from __NEXT_DATA__, meta tags, or JSON-LD
        $coordinates = $this->extractCoordinates($metaData, $jsonLdData, $rawHtml, $nextProperty);

        // Get images from __NEXT_DATA__ gallery and HTML
        $images = $this->extractImages($extracted, $rawHtml, $nextGallery);

        // Get amenities from JSON-LD, HTML, and __NEXT_DATA__ services
        $amenities = $this->extractAmenities($jsonLdData, $extracted, $rawHtml, $nextServices);

        // Determine property type
        $propertyType = $this->resolvePropertyType($jsonLdData, $extracted, $url);

        // Build the final data structure
        return [
            'external_id' => $this->extractExternalId($url, $rawHtml),
            'original_url' => $url,
            'title' => $this->extractTitle($extracted, $jsonLdData, $nextProperty, $nextSeo),
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
            'publisher_name' => $this->extractPublisherName($nextProfile, $extracted),
            'publisher_type' => null,

            // Images
            'images' => $images,

            // Amenities
            'amenities' => $amenities,

            // External codes
            'external_codes' => [],

            // Data quality indicators
            'data_quality' => [
                'has_conflicts' => false,
                'confirmed' => [],
                'conflicts' => [],
            ],

            // Platform metadata
            'platform_metadata' => [
                'json_ld_types' => $this->getJsonLdTypes($jsonLdData),
                'has_next_data' => ! empty($nextData),
            ],
        ];
    }

    /**
     * Extract JSON-LD structured data from HTML.
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
                    $data[] = $decoded;
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
     * Extract __NEXT_DATA__ from HTML.
     * Note: propiedades.com escapes the JSON content, so we need to unescape it.
     *
     * @return array<string, mixed>|null
     */
    protected function extractNextData(string $html): ?array
    {
        // Try flexible pattern that matches __NEXT_DATA__ anywhere in script tag
        if (preg_match('/<script[^>]*__NEXT_DATA__[^>]*>(.+?)<\/script>/si', $html, $match)) {
            $raw = $match[1];

            // First try direct decode
            $decoded = json_decode(trim($raw), true);
            if ($decoded !== null) {
                return $decoded;
            }

            // If that fails, try unescaping (propiedades.com escapes the JSON)
            $unescaped = stripslashes($raw);
            $decoded = json_decode($unescaped, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Fallback: standard id pattern
        if (preg_match('/<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.+?)<\/script>/si', $html, $match)) {
            $decoded = json_decode(trim($match[1]), true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Extract features from __NEXT_DATA__, JSON-LD and HTML.
     *
     * @return array<string, int|float|null>
     */
    protected function extractFeatures(array $jsonLdData, array $extracted, string $rawHtml, array $nextProperty = [], array $nextAmenities = []): array
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

        // Priority 1: __NEXT_DATA__ property and amenities (most reliable for propiedades.com)
        if (! empty($nextProperty['bathrooms'])) {
            $features['bathrooms'] = (float) $nextProperty['bathrooms'];
        }
        if (! empty($nextAmenities['size_ground'])) {
            $features['lot_size_m2'] = (float) $nextAmenities['size_ground'];
        }
        if (! empty($nextProperty['size_house'])) {
            $features['built_size_m2'] = (float) $nextProperty['size_house'];
        }
        if (! empty($nextAmenities['parking_num'])) {
            $features['parking_spots'] = (int) $nextAmenities['parking_num'];
        }
        if (! empty($nextAmenities['age']) && is_numeric($nextAmenities['age'])) {
            $features['age_years'] = (int) $nextAmenities['age'];
        }

        // Priority 2: JSON-LD
        foreach ($jsonLdData as $item) {
            // Handle nested itemOffered
            $property = $item['offers']['itemOffered'] ?? $item;

            if ($features['bedrooms'] === null && isset($property['numberOfBedrooms'])) {
                $features['bedrooms'] = (int) $property['numberOfBedrooms'];
            }
            if ($features['bathrooms'] === null && isset($property['numberOfBathroomsTotal'])) {
                $features['bathrooms'] = (float) $property['numberOfBathroomsTotal'];
            }
            if ($features['lot_size_m2'] === null && isset($property['floorSize']['value'])) {
                $features['lot_size_m2'] = (float) $property['floorSize']['value'];
            }
            if ($features['age_years'] === null && isset($item['yearBuilt'])) {
                $currentYear = (int) date('Y');
                $features['age_years'] = $currentYear - (int) $item['yearBuilt'];
            }
        }

        // Priority 3: HTML patterns
        if ($features['bathrooms'] === null && preg_match('/BAÑOS\s*(\d+(?:\.\d+)?)/i', $rawHtml, $match)) {
            $features['bathrooms'] = (float) $match[1];
        }

        if ($features['parking_spots'] === null && preg_match('/ESTACIONAMIENTOS?\s*(\d+)/i', $rawHtml, $match)) {
            $features['parking_spots'] = (int) $match[1];
        }

        if ($features['lot_size_m2'] === null && preg_match('/[ÁA]REA\s+TERRENO\s*(\d+)\s*m/iu', $rawHtml, $match)) {
            $features['lot_size_m2'] = (float) $match[1];
        }

        if ($features['built_size_m2'] === null && preg_match('/[ÁA]REA\s+CONSTRUIDA?\s*(\d+)\s*m/iu', $rawHtml, $match)) {
            $features['built_size_m2'] = (float) $match[1];
        }

        if ($features['age_years'] === null && preg_match('/Edad\s+del\s+inmueble\s*(\d+)\s*a[ñn]os?/iu', $rawHtml, $match)) {
            $features['age_years'] = (int) $match[1];
        }

        // Extract bedrooms - try "N recámaras" pattern first (more reliable)
        if ($features['bedrooms'] === null) {
            if (preg_match('/(\d+)\s*rec[áa]maras?/iu', $rawHtml, $match)) {
                $features['bedrooms'] = (int) $match[1];
            }
        }

        // Fallback: "RECÁMARAS N" pattern (HTML features section)
        if ($features['bedrooms'] === null) {
            if (preg_match('/REC[ÁA]MARAS\s+(\d+)/iu', $rawHtml, $match)) {
                $features['bedrooms'] = (int) $match[1];
            }
        }

        return $features;
    }

    /**
     * Extract description from various sources.
     */
    protected function extractDescription(array $jsonLdData, array $extracted, string $rawHtml, array $nextProperty = []): ?string
    {
        // Priority 1: __NEXT_DATA__ property description (most complete for propiedades.com)
        if (! empty($nextProperty['description'])) {
            return $this->cleanText($nextProperty['description']);
        }

        // Priority 2: JSON-LD
        foreach ($jsonLdData as $item) {
            $property = $item['offers']['itemOffered'] ?? $item;
            if (! empty($property['description'])) {
                return $this->cleanText($property['description']);
            }
        }

        // Priority 3: CSS extraction
        $description = $extracted['description'] ?? null;
        if (is_array($description)) {
            $description = $description[0] ?? null;
        }

        if (! empty($description)) {
            return $this->cleanText($description);
        }

        // Priority 4: meta description
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $rawHtml, $match)) {
            return $this->cleanText($match[1]);
        }

        return null;
    }

    /**
     * Build operations array from __NEXT_DATA__ and JSON-LD.
     *
     * @return array<array{type: string, price: int|null, currency: string, maintenance_fee: int|null}>
     */
    protected function buildOperations(array $jsonLdData, array $extracted, string $url = '', array $nextProperty = []): array
    {
        $operations = [];

        // Detect operation type from URL (most reliable for propiedades.com)
        $urlBasedType = null;
        if (str_contains($url, '-renta-') || str_contains($url, '/renta')) {
            $urlBasedType = 'rent';
        } elseif (str_contains($url, '-venta-') || str_contains($url, '/venta')) {
            $urlBasedType = 'sale';
        }

        // Priority 1: __NEXT_DATA__ property price
        if (! empty($nextProperty['price'])) {
            $priceStr = $nextProperty['price'];
            // Parse price from format like "$ 34,000 MXN"
            $price = (int) preg_replace('/[^\d]/', '', $priceStr);
            $currency = str_contains($priceStr, 'USD') ? 'USD' : 'MXN';
            $type = $urlBasedType ?? 'rent';

            if ($price > 0) {
                $operations[] = [
                    'type' => $type,
                    'price' => $price,
                    'currency' => $currency,
                    'maintenance_fee' => null,
                ];
            }
        }

        // Priority 2: JSON-LD
        if (empty($operations)) {
            foreach ($jsonLdData as $item) {
                // OfferForLease indicates rent
                if (($item['@type'] ?? '') === 'OfferForLease') {
                    $price = $item['price'] ?? null;
                    if ($price !== null) {
                        $operations[] = [
                            'type' => 'rent',
                            'price' => (int) $price,
                            'currency' => $item['priceCurrency'] ?? 'MXN',
                            'maintenance_fee' => null,
                        ];
                    }
                }

                // Handle nested offers - use URL-based type if available
                // (propiedades.com has a bug where they mark rentals as "Sell")
                if (isset($item['offers']['price'])) {
                    $offer = $item['offers'];
                    $type = $urlBasedType ?? 'rent';

                    // Only trust businessFunction if no URL indication
                    if ($urlBasedType === null && isset($offer['businessFunction'])) {
                        $type = str_contains($offer['businessFunction'], 'Sell') ? 'sale' : 'rent';
                    }

                    $operations[] = [
                        'type' => $type,
                        'price' => (int) $offer['price'],
                        'currency' => $offer['priceCurrency'] ?? 'MXN',
                        'maintenance_fee' => null,
                    ];
                }
            }
        }

        // Deduplicate by price (same price = same operation regardless of type mismatch)
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
     * Parse location from __NEXT_DATA__, JSON-LD and breadcrumbs.
     *
     * @return array<string, string|null>
     */
    protected function parseLocation(array $jsonLdData, array $extracted, string $rawHtml, array $nextProperty = []): array
    {
        $location = [
            'address' => null,
            'colonia' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'location_raw' => null,
        ];

        // Priority 1: __NEXT_DATA__ property
        if (! empty($nextProperty)) {
            $location['address'] = $nextProperty['address'] ?? $nextProperty['short_address'] ?? null;
            $location['colonia'] = $nextProperty['colony'] ?? null;
            $location['city'] = $nextProperty['city'] ?? null;
            $location['state'] = $nextProperty['state'] ?? null;
            $location['postal_code'] = $nextProperty['zipcode'] ?? $nextProperty['zipecode'] ?? null;

            // Build raw location string for geocoder
            $locationParts = array_filter([
                $location['address'],
                $location['colonia'],
                $location['city'],
                $location['state'],
            ]);
            if (! empty($locationParts)) {
                $location['location_raw'] = implode(', ', $locationParts);
            }
        }

        // Priority 2: JSON-LD
        if (empty($location['address'])) {
            foreach ($jsonLdData as $item) {
                $property = $item['offers']['itemOffered'] ?? $item;

                if (isset($property['address'])) {
                    $addr = $property['address'];
                    if (is_array($addr)) {
                        $location['address'] = $addr['streetAddress'] ?? null;
                        if (empty($location['city'])) {
                            $location['city'] = $addr['addressLocality'] ?? null;
                        }
                        if (empty($location['state'])) {
                            $location['state'] = $addr['addressRegion'] ?? null;
                        }
                        if (empty($location['postal_code'])) {
                            $location['postal_code'] = $addr['postalCode'] ?? null;
                        }
                    } elseif (is_string($addr)) {
                        $location['address'] = $addr;
                    }
                }

                // Standalone address field
                if (isset($item['address']) && is_string($item['address']) && empty($location['address'])) {
                    $location['address'] = $item['address'];
                }
            }
        }

        // Extract colonia from address if not already set
        if (empty($location['colonia']) && $location['address'] && preg_match('/Col\.?\s+([^,]+)/i', $location['address'], $match)) {
            $location['colonia'] = trim($match[1]);
        }

        // Fallback: parse from breadcrumbs
        $breadcrumbs = $this->toArray($extracted['breadcrumbs'] ?? []);
        if (count($breadcrumbs) >= 2) {
            // Typical pattern: Inicio / Jalisco / Tlajomulco de Zúñiga / Senderos...
            if (empty($location['state']) && isset($breadcrumbs[1])) {
                $location['state'] = $this->cleanText($breadcrumbs[1]);
            }
            if (empty($location['city']) && isset($breadcrumbs[2])) {
                $location['city'] = $this->cleanText($breadcrumbs[2]);
            }
        }

        return $location;
    }

    /**
     * Extract coordinates from __NEXT_DATA__, meta tags, JSON-LD, or HTML.
     *
     * @return array{latitude: float|null, longitude: float|null}
     */
    protected function extractCoordinates(array $metaData, array $jsonLdData, string $rawHtml, array $nextProperty = []): array
    {
        $lat = null;
        $lng = null;

        // Priority 1: __NEXT_DATA__ property (most reliable for propiedades.com)
        if (! empty($nextProperty['latitude']) && ! empty($nextProperty['longitude'])) {
            $lat = (float) $nextProperty['latitude'];
            $lng = (float) $nextProperty['longitude'];
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

        // Priority 3: JSON-LD geo property
        if ($lat === null) {
            foreach ($jsonLdData as $item) {
                if (isset($item['geo']['latitude'], $item['geo']['longitude'])) {
                    $lat = (float) $item['geo']['latitude'];
                    $lng = (float) $item['geo']['longitude'];
                    break;
                }
            }
        }

        // Priority 4: regex patterns in HTML
        if ($lat === null) {
            if (preg_match('/"latitude"\s*:\s*([-\d.]+)/', $rawHtml, $match)) {
                $lat = (float) $match[1];
            }
            if (preg_match('/"longitude"\s*:\s*([-\d.]+)/', $rawHtml, $match)) {
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
     * Extract images from __NEXT_DATA__ gallery and HTML.
     *
     * @return array<string>
     */
    protected function extractImages(array $extracted, string $rawHtml, array $nextGallery = []): array
    {
        $images = [];
        $seenIds = [];

        // Priority 1: __NEXT_DATA__ gallery (complete list for propiedades.com)
        // When this is available, use it exclusively as it contains all listing images
        // Note: These images are trusted and don't need UUID filtering
        if (! empty($nextGallery)) {
            foreach ($nextGallery as $item) {
                // Gallery items have 'image' filename, need to construct full URL
                if (isset($item['image']) && is_string($item['image'])) {
                    $filename = $item['image'];
                    // Use 1200x507 resolution for best quality
                    $url = "https://propiedadescom.s3.amazonaws.com/files/1200x507/{$filename}";
                    $images[] = $url;
                    // Also add to seenIds if UUID exists to avoid dupes later
                    if (preg_match('/([a-f0-9-]{36})\./i', $filename, $match)) {
                        $seenIds[$match[1]] = $url;
                    }
                } elseif (isset($item['url'])) {
                    $images[] = $item['url'];
                } elseif (isset($item['src'])) {
                    $images[] = $item['src'];
                } elseif (is_string($item) && ! empty($item)) {
                    $images[] = $item;
                }
            }

            // When using __NEXT_DATA__ gallery, return directly without UUID filtering
            // since the gallery is authoritative and complete
            return array_values(array_unique($images));
        } else {
            // Fallback: combine CSS extraction + HTML parsing when no __NEXT_DATA__

            // Collect from CSS extraction
            $cssImages = $this->toArray($extracted['gallery_images'] ?? []);
            $cssImages = array_merge($cssImages, $this->toArray($extracted['main_image'] ?? []));

            foreach ($cssImages as $url) {
                if (empty($url)) {
                    continue;
                }
                $images[] = $url;
            }

            // Extract from JSON-LD
            if (preg_match_all('/"image"\s*:\s*\[([^\]]+)\]/s', $rawHtml, $matches)) {
                foreach ($matches[1] as $imageArray) {
                    if (preg_match_all('/"([^"]+\.(?:jpg|jpeg|png|webp))"/i', $imageArray, $urlMatches)) {
                        foreach ($urlMatches[1] as $url) {
                            $images[] = $url;
                        }
                    }
                }
            }

            // Extract from HTML - all propiedades.com CDN images
            if (preg_match_all('/https?:\/\/(?:cdn\.propiedades\.com|propiedadescom\.s3\.amazonaws\.com)\/files\/[^"\'>\s]+\.(?:jpg|jpeg|png|webp)/i', $rawHtml, $matches)) {
                foreach ($matches[0] as $url) {
                    $images[] = $url;
                }
            }
        }

        // Deduplicate and upgrade resolution
        // Only include images with UUID patterns (actual listing images)
        // Excludes related listing thumbnails which have slug-based patterns
        $unique = [];
        foreach ($images as $url) {
            // Only accept images with UUID patterns (propiedades.com listing images)
            if (preg_match('/([a-f0-9-]{36})\.(jpg|jpeg|png|webp)/i', $url, $match)) {
                $imageId = $match[1];

                // Skip if we've seen this image
                if (isset($seenIds[$imageId])) {
                    // Keep higher resolution version
                    if ($this->getImageResolution($url) > $this->getImageResolution($seenIds[$imageId])) {
                        // Remove old, add new
                        $unique = array_filter($unique, fn ($u) => ! str_contains($u, $imageId));
                        $unique[] = $this->upgradeImageResolution($url);
                        $seenIds[$imageId] = $url;
                    }

                    continue;
                }

                $seenIds[$imageId] = $url;
                $unique[] = $this->upgradeImageResolution($url);
            }
            // Skip images without UUID patterns (related listings, etc.)
        }

        return array_values($unique);
    }

    /**
     * Get image resolution score from URL.
     */
    protected function getImageResolution(string $url): int
    {
        if (preg_match('/\/files\/(\d+)x(\d+)\//', $url, $match)) {
            return (int) $match[1] * (int) $match[2];
        }

        return 0;
    }

    /**
     * Upgrade image URL to higher resolution.
     */
    protected function upgradeImageResolution(string $url): string
    {
        // Upgrade to 1200x507 (largest commonly available)
        return preg_replace('/\/files\/\d+x\d+\//', '/files/1200x507/', $url);
    }

    /**
     * Extract amenities from JSON-LD and HTML.
     *
     * @return array<string>
     */
    protected function extractAmenities(array $jsonLdData, array $extracted, string $rawHtml, array $nextServices = []): array
    {
        $amenities = [];

        // From __NEXT_DATA__ services (most reliable source)
        if (! empty($nextServices)) {
            foreach ($nextServices as $key => $value) {
                // Services are like {"cistern": "Cisterna", "garden": "Jardín"}
                if (is_string($value) && ! empty($value)) {
                    $amenities[] = $value;
                }
            }
        }

        // From JSON-LD amenityFeature
        foreach ($jsonLdData as $item) {
            $property = $item['offers']['itemOffered'] ?? $item;
            if (isset($property['amenityFeature']) && is_array($property['amenityFeature'])) {
                foreach ($property['amenityFeature'] as $feature) {
                    if (isset($feature['name']) && ($feature['value'] ?? false)) {
                        $amenities[] = $feature['name'];
                    }
                }
            }
        }

        // From HTML - extract amenities section text
        if (preg_match('/Amenidades[^<]*<\/h2>(.{0,2000})/si', $rawHtml, $match)) {
            $section = $match[1];
            // Extract individual amenity items
            if (preg_match_all('/>([^<]{2,50})</u', $section, $items)) {
                foreach ($items[1] as $item) {
                    $item = trim($item);
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
        // Priority 1: Check nested itemOffered.@type (most specific in propiedades.com)
        foreach ($jsonLdData as $item) {
            $property = $item['offers']['itemOffered'] ?? null;
            if ($property && isset($property['@type'])) {
                return match ($property['@type']) {
                    'Apartment' => 'apartment',
                    'House', 'SingleFamilyResidence' => 'house',
                    default => 'house',
                };
            }
        }

        // Priority 2: From URL pattern (reliable - propiedades.com has clean URLs)
        if (preg_match('/\/(casa|departamento|terreno|local|oficina|bodega)[-s]?(?:-en)?-/i', $url, $match)) {
            $mappings = $this->config->propertyTypes();

            return $mappings[strtolower($match[1])] ?? 'house';
        }

        // Priority 3: Standalone JSON-LD @type (can be misleading on propiedades.com)
        foreach ($jsonLdData as $item) {
            $type = $item['@type'] ?? null;
            if (in_array($type, ['Apartment', 'House', 'SingleFamilyResidence'])) {
                return match ($type) {
                    'Apartment' => 'apartment',
                    'House', 'SingleFamilyResidence' => 'house',
                    default => 'house',
                };
            }
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
     * Extract external ID from URL or HTML.
     */
    protected function extractExternalId(string $url, string $rawHtml): ?string
    {
        // From URL
        $id = $this->config->extractExternalId($url);
        if ($id !== null) {
            return $id;
        }

        // From HTML content (ID: 30554556)
        if (preg_match('/ID:\s*(\d{8})/', $rawHtml, $match)) {
            return $match[1];
        }

        // From JSON content
        if (preg_match('/"id"\s*:\s*"?(\d{8})"?/', $rawHtml, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * Extract title from __NEXT_DATA__ seo, extraction, or JSON-LD.
     */
    protected function extractTitle(array $extracted, array $jsonLdData, array $nextProperty = [], array $nextSeo = []): ?string
    {
        // Priority 1: __NEXT_DATA__ seo.meta_title (most complete title)
        if (! empty($nextSeo['meta_title'])) {
            return $this->cleanText($nextSeo['meta_title']);
        }

        // Priority 2: CSS extraction (usually has full title)
        $title = $extracted['title'] ?? null;
        if (is_array($title)) {
            $title = $title[0] ?? null;
        }

        if (! empty($title)) {
            return $this->cleanText($title);
        }

        // Priority 3: __NEXT_DATA__ keywords_title (often truncated)
        if (! empty($nextProperty['keywords_title'])) {
            return $this->cleanText($nextProperty['keywords_title']);
        }

        // Priority 4: JSON-LD
        foreach ($jsonLdData as $item) {
            if (! empty($item['name'])) {
                return $this->cleanText($item['name']);
            }
        }

        return null;
    }

    /**
     * Extract publisher name from __NEXT_DATA__ profile or CSS extraction.
     */
    protected function extractPublisherName(array $nextProfile, array $extracted): ?string
    {
        // Priority 1: __NEXT_DATA__ profile (has name + lastname)
        if (! empty($nextProfile['name'])) {
            $name = trim($nextProfile['name']);
            if (! empty($nextProfile['lastname'])) {
                $name .= ' '.trim($nextProfile['lastname']);
            }

            return $this->cleanText($name);
        }

        // Priority 2: CSS extraction
        $publisherName = $extracted['publisher_name'] ?? null;
        if (is_array($publisherName)) {
            $publisherName = $publisherName[0] ?? null;
        }

        if (! empty($publisherName)) {
            return $this->cleanText($publisherName);
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
