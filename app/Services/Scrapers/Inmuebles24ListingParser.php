<?php

namespace App\Services\Scrapers;

class Inmuebles24ListingParser
{
    public function __construct(protected Inmuebles24Config $config) {}

    /**
     * Parse listing data from ZenRows CSS-extracted data + raw HTML for JS variables.
     *
     * @param  array<string, mixed>  $extracted  Data from ZenRows css_extractor
     * @param  string  $rawHtml  Raw HTML for JavaScript variable extraction
     * @param  string  $url  Original listing URL
     * @return array<string, mixed>
     */
    public function parse(array $extracted, string $rawHtml, string $url): array
    {
        // Extract JavaScript variables from raw HTML
        $jsData = $this->extractJavaScriptVars($rawHtml);

        // Extract dataLayer data (richer structured info)
        $dataLayerData = $this->extractDataLayer($rawHtml);

        // Extract features from icon-based elements
        $features = $this->extractFeatures($extracted, $dataLayerData);

        // Build operations array (price/rent info) - now supports multiple operations
        $operations = $this->buildOperations($jsData, $dataLayerData, $rawHtml);

        // Parse location (enhanced with dataLayer)
        $location = $this->parseLocation($extracted, $jsData, $dataLayerData);

        // Parse description for additional data
        $description = $this->cleanDescription($extracted['description'] ?? '');
        $descriptionData = $this->parseDescription($description, $extracted['title'] ?? '');

        // Merge amenities from extraction and description
        $amenities = $this->mergeAmenities(
            $this->standardizeAmenities($this->toArray($extracted['amenities'] ?? [])),
            $descriptionData['amenities']
        );

        // Get images (enhanced extraction)
        $images = $this->extractImages($extracted, $rawHtml);

        // Extract coordinates with multiple fallbacks
        $coordinates = $this->extractCoordinates($jsData, $rawHtml);

        // Build the final data structure
        return [
            'external_id' => $this->extractExternalId($url) ?? $jsData['posting_id'] ?? null,
            'original_url' => $url,
            'title' => $this->cleanText($extracted['title'] ?? null),
            'description' => $description,

            // Operations (sale/rent with prices) - now supports multiple
            'operations' => $operations,

            // Features
            'bedrooms' => $features['bedrooms'],
            'bathrooms' => $features['bathrooms'],
            'half_bathrooms' => $features['half_bathrooms'],
            'parking_spots' => $features['parking_spots'],
            'lot_size_m2' => $features['lot_size_m2'],
            'built_size_m2' => $features['built_size_m2'],
            'age_years' => $features['age_years'],
            'property_type' => $this->resolvePropertyType($jsData, $dataLayerData, $extracted),
            'property_subtype' => $descriptionData['subtype'],

            // Location
            'address' => $location['address'],
            'colonia' => $location['colonia'],
            'city' => $location['city'],
            'state' => $location['state'],
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],

            // Publisher
            'publisher_id' => $jsData['publisher_id'] ?? null,
            'publisher_name' => $jsData['publisher_name'] ?? $this->cleanText($extracted['publisher_name'] ?? null),
            'publisher_type' => $this->resolvePublisherType($jsData, $dataLayerData),
            'publisher_url' => $this->buildPublisherUrl($jsData),
            'publisher_logo' => $jsData['publisher_logo'] ?? null,
            'whatsapp' => $this->parseWhatsApp($extracted['whatsapp_link'] ?? null, $jsData),

            // Images
            'images' => $images,

            // Amenities
            'amenities' => $amenities,

            // External codes
            'external_codes' => array_filter([
                'easybroker' => $descriptionData['easybroker_id'],
            ]),

            // Data quality indicators
            'data_quality' => $descriptionData['data_quality'],

            // Platform metadata
            'platform_metadata' => [
                'province_id' => $jsData['province_id'] ?? null,
                'city_id' => $jsData['city_id'] ?? null,
                'neighborhood_id' => $jsData['neighborhood_id'] ?? null,
                'property_type_id' => $jsData['property_type_id'] ?? null,
                'publisher_type_id' => $jsData['publisher_type_id'] ?? null,
                'posting_id' => $jsData['posting_id'] ?? null,
                'dataLayer' => $dataLayerData, // Include for debugging/LLM analysis
            ],
        ];
    }

    /**
     * Extract JavaScript variables from HTML.
     *
     * @return array<string, mixed>
     */
    protected function extractJavaScriptVars(string $html): array
    {
        $data = [];

        foreach ($this->config->jsPatterns() as $key => $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $value = $matches[1];

                // Convert numeric strings to numbers
                if (is_numeric($value)) {
                    $value = str_contains($value, '.') ? (float) $value : (int) $value;
                }

                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Extract data from dataLayer JavaScript object.
     * dataLayer contains rich structured data about the listing.
     *
     * @return array<string, mixed>
     */
    protected function extractDataLayer(string $html): array
    {
        $data = [];

        foreach ($this->config->dataLayerPatterns() as $key => $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $value = $matches[1];

                // Convert numeric strings to numbers
                if (is_numeric($value)) {
                    $value = str_contains($value, '.') ? (float) $value : (int) $value;
                }

                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Extract coordinates with multiple fallback patterns.
     *
     * @return array{latitude: float|null, longitude: float|null}
     */
    protected function extractCoordinates(array $jsData, string $html): array
    {
        $coordinates = [
            'latitude' => null,
            'longitude' => null,
        ];

        // Try from jsData first
        if (isset($jsData['latitude']) && isset($jsData['longitude'])) {
            $coordinates['latitude'] = (float) $jsData['latitude'];
            $coordinates['longitude'] = (float) $jsData['longitude'];

            return $coordinates;
        }

        // Try various patterns in HTML
        $patterns = [
            // JSON-LD format
            '/"geo"\s*:\s*\{[^}]*"latitude"\s*:\s*([-\d.]+)[^}]*"longitude"\s*:\s*([-\d.]+)/s',
            // dataLayer format
            '/"latitud"\s*:\s*"?([-\d.]+)"?\s*,\s*"longitud"\s*:\s*"?([-\d.]+)"?/',
            // Map initialization
            '/initMap\s*\([^)]*\{\s*lat:\s*([-\d.]+)\s*,\s*lng:\s*([-\d.]+)/s',
            // Leaflet/Google Maps marker
            '/new\s+(?:google\.maps\.)?LatLng\s*\(\s*([-\d.]+)\s*,\s*([-\d.]+)/s',
            // Generic coordinate pattern
            '/coordinates?\s*[:=]\s*\[\s*([-\d.]+)\s*,\s*([-\d.]+)\s*\]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $lat = (float) $matches[1];
                $lng = (float) $matches[2];

                // Validate coordinates are in Mexico range
                if ($lat >= 14 && $lat <= 33 && $lng >= -118 && $lng <= -86) {
                    $coordinates['latitude'] = $lat;
                    $coordinates['longitude'] = $lng;

                    return $coordinates;
                }
            }
        }

        return $coordinates;
    }

    /**
     * Extract features from icon-based elements.
     *
     * @return array<string, int|null>
     */
    protected function extractFeatures(array $extracted, array $dataLayerData = []): array
    {
        $features = [
            'bedrooms' => $this->parseNumber($extracted['bedrooms_text'] ?? null),
            'bathrooms' => $this->parseNumber($extracted['bathrooms_text'] ?? null),
            'half_bathrooms' => $this->parseNumber($extracted['half_bathrooms_text'] ?? null),
            'parking_spots' => $this->parseNumber($extracted['parking_text'] ?? null),
            'lot_size_m2' => $this->parseNumber($extracted['area_total_text'] ?? null),
            'built_size_m2' => $this->parseNumber($extracted['area_built_text'] ?? null),
            'age_years' => $this->parseNumber($extracted['age_text'] ?? null),
        ];

        // Fallback: parse from feature_items if icon selectors didn't match
        $featureItems = $this->toArray($extracted['feature_items'] ?? []);
        if (! empty($featureItems)) {
            $features = $this->parseFeatureItems($featureItems, $features);
        }

        // Use dataLayer as fallback for missing features
        if ($features['bedrooms'] === null && isset($dataLayerData['bedrooms'])) {
            $features['bedrooms'] = (int) $dataLayerData['bedrooms'];
        }
        if ($features['bathrooms'] === null && isset($dataLayerData['bathrooms'])) {
            $features['bathrooms'] = (int) $dataLayerData['bathrooms'];
        }
        if ($features['lot_size_m2'] === null && isset($dataLayerData['area'])) {
            $features['lot_size_m2'] = (int) $dataLayerData['area'];
        }

        return $features;
    }

    /**
     * Parse features from generic feature items list.
     *
     * @param  array<string>  $items
     * @param  array<string, int|null>  $features
     * @return array<string, int|null>
     */
    protected function parseFeatureItems(array $items, array $features): array
    {
        foreach ($items as $item) {
            $itemLower = mb_strtolower($item);
            $number = $this->parseNumber($item);

            if ($number === null) {
                continue;
            }

            // Match patterns in the text
            if (preg_match('/rec[aá]mara|dormitorio|\brec\.?$/u', $itemLower) && $features['bedrooms'] === null) {
                $features['bedrooms'] = $number;
            } elseif (preg_match('/medio\s*ba[ñn]o/u', $itemLower) && $features['half_bathrooms'] === null) {
                $features['half_bathrooms'] = $number;
            } elseif (preg_match('/ba[ñn]o/u', $itemLower) && $features['bathrooms'] === null) {
                $features['bathrooms'] = $number;
            } elseif (preg_match('/estac|cochera|parking/u', $itemLower) && $features['parking_spots'] === null) {
                $features['parking_spots'] = $number;
            } elseif (preg_match('/m[²2]\s*(lote|terreno|total)/u', $itemLower) && $features['lot_size_m2'] === null) {
                $features['lot_size_m2'] = $number;
            } elseif (preg_match('/m[²2]\s*(constr|cubierta)/u', $itemLower) && $features['built_size_m2'] === null) {
                $features['built_size_m2'] = $number;
            } elseif (preg_match('/a[ñn]o|antig[üu]edad/u', $itemLower) && $features['age_years'] === null) {
                $features['age_years'] = $number;
            }
        }

        return $features;
    }

    /**
     * Build operations array from JavaScript data and dataLayer.
     * Supports multiple operations (e.g., same property for sale AND rent).
     *
     * @return array<array{type: string, price: int, currency: string, maintenance_fee: int|null}>
     */
    protected function buildOperations(array $jsData, array $dataLayerData, string $html): array
    {
        $operations = [];
        $operationTypes = $this->config->operationTypes();
        $currencyTypes = $this->config->currencyTypes();
        $maintenanceFee = $this->extractMaintenanceFee($html);

        // Primary operation from JS data
        if (isset($jsData['price']) && isset($jsData['operation_type_id'])) {
            $operations[] = [
                'type' => $operationTypes[$jsData['operation_type_id']] ?? 'sale',
                'price' => (int) $jsData['price'],
                'currency' => $currencyTypes[$jsData['currency_id'] ?? 10] ?? 'MXN',
                'maintenance_fee' => $maintenanceFee,
            ];
        }

        // Check dataLayer for additional operations
        // dataLayer may have precioVenta and precioAlquiler separately
        $salePrice = $this->parsePriceFromDataLayer($dataLayerData['sale_price'] ?? null);
        $rentPrice = $this->parsePriceFromDataLayer($dataLayerData['rent_price'] ?? null);

        // Add sale operation if not already present and price exists
        if ($salePrice && ! $this->hasOperationType($operations, 'sale')) {
            $operations[] = [
                'type' => 'sale',
                'price' => $salePrice['amount'],
                'currency' => $salePrice['currency'],
                'maintenance_fee' => null,
            ];
        }

        // Add rent operation if not already present and price exists
        if ($rentPrice && ! $this->hasOperationType($operations, 'rent')) {
            $operations[] = [
                'type' => 'rent',
                'price' => $rentPrice['amount'],
                'currency' => $rentPrice['currency'],
                'maintenance_fee' => $maintenanceFee, // Maintenance usually applies to rent
            ];
        }

        // Fallback: try to extract from HTML if no operations found
        if (empty($operations)) {
            $operations = $this->extractOperationsFromHtml($html, $maintenanceFee);
        }

        return $operations;
    }

    /**
     * Parse price from dataLayer format like "MN 2000" or "USD 150000".
     *
     * @return array{amount: int, currency: string}|null
     */
    protected function parsePriceFromDataLayer(?string $priceText): ?array
    {
        if (! $priceText) {
            return null;
        }

        // Format: "MN 2000" or "USD 150000"
        if (preg_match('/^(MN|USD)\s*([\d,]+)/i', $priceText, $matches)) {
            return [
                'amount' => (int) str_replace(',', '', $matches[2]),
                'currency' => strtoupper($matches[1]) === 'MN' ? 'MXN' : 'USD',
            ];
        }

        // Just a number
        if (preg_match('/^([\d,]+)$/', trim($priceText), $matches)) {
            return [
                'amount' => (int) str_replace(',', '', $matches[1]),
                'currency' => 'MXN',
            ];
        }

        return null;
    }

    /**
     * Check if operations array already has a specific type.
     *
     * @param  array<array{type: string}>  $operations
     */
    protected function hasOperationType(array $operations, string $type): bool
    {
        foreach ($operations as $op) {
            if (($op['type'] ?? '') === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract operations from HTML when other methods fail.
     *
     * @return array<array{type: string, price: int, currency: string, maintenance_fee: int|null}>
     */
    protected function extractOperationsFromHtml(string $html, ?int $maintenanceFee): array
    {
        $operations = [];

        // Pattern for price with operation type
        $patterns = [
            // "Venta: $1,500,000 MXN" or "En venta $1,500,000"
            '/(?:venta|en\s+venta|precio\s+venta)[:\s]*\$?\s*([\d,]+)\s*(?:MXN|MN|USD)?/i' => 'sale',
            // "Renta: $15,000 MXN/mes" or "En renta $15,000"
            '/(?:renta|en\s+renta|precio\s+renta|alquiler)[:\s]*\$?\s*([\d,]+)\s*(?:MXN|MN|USD)?/i' => 'rent',
        ];

        foreach ($patterns as $pattern => $type) {
            if (preg_match($pattern, $html, $matches)) {
                $price = (int) str_replace(',', '', $matches[1]);
                if ($price > 0 && ! $this->hasOperationType($operations, $type)) {
                    // Detect currency
                    $currency = 'MXN';
                    if (preg_match('/USD/i', $matches[0])) {
                        $currency = 'USD';
                    }

                    $operations[] = [
                        'type' => $type,
                        'price' => $price,
                        'currency' => $currency,
                        'maintenance_fee' => $type === 'rent' ? $maintenanceFee : null,
                    ];
                }
            }
        }

        return $operations;
    }

    /**
     * Extract maintenance fee from HTML.
     */
    protected function extractMaintenanceFee(string $html): ?int
    {
        $patterns = [
            '/Mantenimiento\s*(?:MN|USD|\$)?\s*([\d,]+)/i',
            '/MN\s*([\d,]+)\s*Mantenimiento/i',
            '/mantenimiento[:\s]*\$?\s*([\d,]+)/i',
            '/\$\s*([\d,]+)\s*(?:de\s+)?mant/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return (int) str_replace(',', '', $matches[1]);
            }
        }

        return null;
    }

    /**
     * Parse location from extracted data, JS variables, and dataLayer.
     *
     * @return array{address: string|null, colonia: string|null, city: string|null, state: string|null}
     */
    protected function parseLocation(array $extracted, array $jsData, array $dataLayerData = []): array
    {
        $location = [
            'address' => null,
            'colonia' => null,
            'city' => null,
            'state' => null,
        ];

        // Parse from location header (e.g., "Calle 1, Col. Centro, Guadalajara, Jalisco")
        $header = $this->cleanText($extracted['location_header'] ?? null);
        if ($header) {
            $parts = array_map('trim', explode(',', $header));
            if (count($parts) >= 1) {
                $location['address'] = $parts[0];
            }
            if (count($parts) >= 2) {
                $location['colonia'] = $parts[1];
            }
            if (count($parts) >= 3) {
                $location['city'] = $parts[2];
            }
            if (count($parts) >= 4) {
                $location['state'] = $parts[3];
            }
        }

        // Try to get state/city from breadcrumbs if not found
        $breadcrumbs = $this->toArray($extracted['breadcrumbs'] ?? []);
        if (! $location['state'] && count($breadcrumbs) > 0) {
            $mexicanStates = ['jalisco', 'nuevo león', 'cdmx', 'ciudad de méxico', 'estado de méxico',
                'querétaro', 'puebla', 'yucatán', 'quintana roo', 'guanajuato'];

            foreach ($breadcrumbs as $i => $crumb) {
                $crumbLower = mb_strtolower($crumb);
                foreach ($mexicanStates as $state) {
                    if (str_contains($crumbLower, $state)) {
                        $location['state'] = $location['state'] ?? $crumb;
                        if (isset($breadcrumbs[$i + 1])) {
                            $location['city'] = $location['city'] ?? $breadcrumbs[$i + 1];
                        }
                        break 2;
                    }
                }
            }
        }

        // Use dataLayer as fallback for missing location fields
        if (! $location['colonia'] && isset($dataLayerData['neighborhood'])) {
            $location['colonia'] = $dataLayerData['neighborhood'];
        }
        if (! $location['city'] && isset($dataLayerData['city'])) {
            $location['city'] = $dataLayerData['city'];
        }
        if (! $location['state'] && isset($dataLayerData['state'])) {
            $location['state'] = $dataLayerData['state'];
        }

        return $location;
    }

    /**
     * Parse description for additional structured data.
     *
     * @return array{easybroker_id: string|null, amenities: array, subtype: string|null, data_quality: array}
     */
    protected function parseDescription(?string $description, ?string $title): array
    {
        $result = [
            'easybroker_id' => null,
            'amenities' => [],
            'subtype' => null,
            'data_quality' => [
                'has_conflicts' => false,
                'confirmed' => [],
                'conflicts' => [],
            ],
        ];

        if (! $description) {
            return $result;
        }

        $textUpper = mb_strtoupper($description);
        $combinedUpper = mb_strtoupper(($title ?? '').' '.$description);

        // Extract EasyBroker ID
        $result['easybroker_id'] = $this->extractEasyBrokerId($description);

        // Extract amenities from description
        $result['amenities'] = $this->extractAmenitiesFromDescription($textUpper);

        // Detect property subtype
        $result['subtype'] = $this->detectSubtype($combinedUpper);

        return $result;
    }

    /**
     * Extract EasyBroker ID from description.
     */
    protected function extractEasyBrokerId(string $description): ?string
    {
        $patterns = [
            '/EASYBROKER\s*ID:\s*(EB-[A-Z0-9]+)/i',
            '/EB-[A-Z0-9]{6,}/i',
            '/EASY\s*BROKER[:\s]*(EB-[A-Z0-9]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                $id = $matches[1] ?? $matches[0];
                if (str_starts_with($id, 'EB-')) {
                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * Extract amenities from description text.
     *
     * @return array<string>
     */
    protected function extractAmenitiesFromDescription(string $textUpper): array
    {
        $amenities = [];

        $patterns = [
            '/ALBERCA|PISCINA/' => 'pool',
            '/GIMNASIO|GYM\b/' => 'gym',
            '/SEGURIDAD\s*24|VIGILANCIA\s*24/' => 'security_24h',
            '/ELEVADOR(?:ES)?|ASCENSOR/' => 'elevator',
            '/ROOF\s*GARDEN|ROOF\s*TOP|ROOFTOP/' => 'rooftop',
            '/TERRAZA/' => 'terrace',
            '/BALCON(?:ES)?/' => 'balcony',
            '/JACUZZI|JACUZI/' => 'jacuzzi',
            '/[ÁA]REA\s+DE\s+BBQ|ASADOR(?:ES)?|PARRILLA/' => 'bbq_area',
            '/PET\s*FRIENDLY|ACEPTA\s+MASCOTAS/' => 'pet_friendly',
            '/\bAMUEBLADO\b/' => 'furnished',
            '/[ÁA]REA\s+DE\s+JUEGOS|LUDOTECA|PLAYGROUND/' => 'playground',
            '/CUARTO\s+DE\s+SERVICIO/' => 'service_room',
            '/BODEGA(?!\s+INDUSTRIAL)/' => 'storage',
            '/AIRE\s+ACONDICIONADO/' => 'ac',
            '/COCINA\s+INTEGRAL/' => 'integrated_kitchen',
            '/CO-?WORKING|COWORKING/' => 'coworking',
            '/\bSPA\b|VAPOR|SAUNA/' => 'spa',
        ];

        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $textUpper)) {
                $amenities[] = $name;
            }
        }

        return array_unique($amenities);
    }

    /**
     * Detect property subtype from text.
     */
    protected function detectSubtype(string $textUpper): ?string
    {
        foreach ($this->config->subtypePatterns() as $pattern => $subtype) {
            if (preg_match($pattern, $textUpper)) {
                return $subtype;
            }
        }

        return null;
    }

    /**
     * Standardize amenities using config mappings.
     *
     * @param  array<string>  $rawAmenities
     * @return array<string>
     */
    protected function standardizeAmenities(array $rawAmenities): array
    {
        $mappings = $this->config->amenityMappings();
        $standardized = [];

        foreach ($rawAmenities as $raw) {
            $rawLower = mb_strtolower(trim($raw));
            $found = false;

            foreach ($mappings as $keyword => $standard) {
                if (str_contains($rawLower, $keyword)) {
                    if (! in_array($standard, $standardized)) {
                        $standardized[] = $standard;
                    }
                    $found = true;
                    break;
                }
            }

            // Keep unmapped amenities if they're meaningful
            if (! $found && strlen($rawLower) > 2) {
                $cleaned = preg_replace('/[^a-záéíóúñü\s]/i', '', $rawLower);
                if ($cleaned && ! in_array($cleaned, $standardized)) {
                    $standardized[] = $cleaned;
                }
            }
        }

        return $standardized;
    }

    /**
     * Merge amenities from different sources.
     *
     * @return array<string>
     */
    protected function mergeAmenities(array $htmlAmenities, array $descriptionAmenities): array
    {
        return array_values(array_unique(array_merge($htmlAmenities, $descriptionAmenities)));
    }

    /**
     * Extract images from extracted data and raw HTML.
     *
     * @return array<string>
     */
    protected function extractImages(array $extracted, string $rawHtml): array
    {
        $imagesByHash = []; // Dedupe by image hash/ID

        // Helper to add image - extracts the unique image ID and keeps highest quality
        $addImage = function (string $url) use (&$imagesByHash) {
            $url = $this->cleanImageUrl($url);
            if (! $url) {
                return;
            }

            // Extract the unique image ID (e.g., 1530108201 from the URL)
            // Pattern: /avisos/.../.../1530108201.jpg
            if (preg_match('/\/(\d{9,12})\.(?:jpg|jpeg|png|webp)/i', $url, $match)) {
                $imageId = $match[1];

                // Prefer 1200x1200, then 720x532, then others
                $priority = 0;
                if (str_contains($url, '1200x1200')) {
                    $priority = 3;
                } elseif (str_contains($url, '720x532') || str_contains($url, '730x532')) {
                    $priority = 2;
                } elseif (str_contains($url, '360x266')) {
                    $priority = 1;
                }

                // Prefer non-resize URLs over resize URLs for same resolution
                if (! str_contains($url, '/resize/')) {
                    $priority += 0.5;
                }

                if (! isset($imagesByHash[$imageId]) || $imagesByHash[$imageId]['priority'] < $priority) {
                    $imagesByHash[$imageId] = ['url' => $url, 'priority' => $priority];
                }
            } else {
                // For non-standard URLs, use the URL itself as key
                $hash = md5($url);
                if (! isset($imagesByHash[$hash])) {
                    $imagesByHash[$hash] = ['url' => $url, 'priority' => 0];
                }
            }
        };

        // Try all CSS-extracted image sources
        $cssImageKeys = [
            'gallery_images',
            'carousel_images',
            'picture_images',
            'multimedia_images',
            'all_listing_images',
            'preview_gallery_images',
            'modal_gallery_images',
        ];
        foreach ($cssImageKeys as $key) {
            foreach ($this->toArray($extracted[$key] ?? []) as $url) {
                $addImage($url);
            }
        }

        // Extract from __NEXT_DATA__ (Next.js apps store full data here)
        if (preg_match('/<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.+?)<\/script>/s', $rawHtml, $nextDataMatch)) {
            $nextData = @json_decode($nextDataMatch[1], true);
            if ($nextData) {
                $this->extractImagesFromJson($nextData, $addImage);
            }
        }

        // Extract from JSON-LD structured data
        if (preg_match('/"image"\s*:\s*\[([^\]]+)\]/s', $rawHtml, $jsonLdMatch)) {
            preg_match_all('/"([^"]+\.(?:jpg|jpeg|png|webp)[^"]*)"/i', $jsonLdMatch[1], $ldImages);
            foreach ($ldImages[1] ?? [] as $url) {
                $addImage($url);
            }
        }

        // Extract from initialState or similar React state objects
        $statePatterns = [
            '/(?:initialState|__PRELOADED_STATE__|window\.__data__|window\.state)\s*=\s*(\{.{500,}?\});/s',
            '/(?:props|pageProps|posting|listing)\s*[:=]\s*(\{[^}]{500,})/s',
        ];
        foreach ($statePatterns as $pattern) {
            if (preg_match($pattern, $rawHtml, $stateMatch)) {
                // Try to parse as JSON (may fail if not valid JSON)
                $jsonStr = $stateMatch[1];
                // Fix common issues with JS objects that aren't valid JSON
                $jsonStr = preg_replace('/([{,])\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $jsonStr);
                $data = @json_decode($jsonStr, true);
                if ($data) {
                    $this->extractImagesFromJson($data, $addImage);
                }
            }
        }

        // Extract all image URLs with various resolution patterns
        // The site stores images in JSON with keys like url1200x1200, resizeUrl1200x1200, etc.
        $jsPatterns = [
            // Match url1200x1200, resizeUrl1200x1200, url720x532, url730x532, url360x266
            '/"(?:resize)?[Uu]rl\d+x\d+"\s*:\s*"(https?:\/\/[^"]+)"/i',
            // Match url or src with avisos path
            '/"(?:url|src)"\s*:\s*"(https?:\/\/[^"]+\/avisos\/[^"]+\.(?:jpg|jpeg|png|webp)[^"]*)"/i',
            // Legacy patterns
            '/url1200x1200["\']\s*[:=]\s*["\']([^"\']+)["\']/i',
            '/"(?:url|src|image|fullUrl|originalUrl)"\s*:\s*"(https?:\/\/[^"]+img[^"]*\.(?:jpg|jpeg|png|webp)[^"]*)"/i',
        ];

        foreach ($jsPatterns as $pattern) {
            preg_match_all($pattern, $rawHtml, $matches);
            foreach ($matches[1] ?? [] as $url) {
                $addImage($url);
            }
        }

        // Extract from gallery image data attributes or background-image styles
        preg_match_all('/(?:data-src|data-lazy|data-original|background-image[^)]+url\()["\']?([^"\')\s]+(?:inmuebles24|img)[^"\')\s]+\.(?:jpg|jpeg|png|webp))["\']?/i', $rawHtml, $attrMatches);
        foreach ($attrMatches[1] ?? [] as $url) {
            $addImage($url);
        }

        // Look for image/pictures arrays in JavaScript
        $arrayPatterns = [
            '/(?:pictures?|images?|photos?|multimedia|gallery)\s*[:=]\s*\[([^\]]{100,})\]/is',
            '/"(?:pictures?|images?|photos?|multimedia)"\s*:\s*\[([^\]]{100,})\]/is',
        ];
        foreach ($arrayPatterns as $pattern) {
            if (preg_match_all($pattern, $rawHtml, $arrayMatches)) {
                foreach ($arrayMatches[1] as $arrayContent) {
                    preg_match_all('/"(https?:\/\/[^"]+\.(?:jpg|jpeg|png|webp)[^"]*)"/i', $arrayContent, $pictureUrls);
                    foreach ($pictureUrls[1] ?? [] as $url) {
                        $addImage($url);
                    }
                }
            }
        }

        // Final fallback: find any inmuebles24/img CDN URLs with image extensions
        preg_match_all('/https?:\/\/[^"\'\s]+(?:inmuebles24|img\.clasificados)[^"\'\s]+\.(?:jpg|jpeg|png|webp)/i', $rawHtml, $fallbackMatches);
        foreach ($fallbackMatches[0] ?? [] as $url) {
            // Only add if it looks like a listing image (contains /avisos/ or /pictures/)
            if (preg_match('/\/(?:avisos|pictures|multimedia|images)\//i', $url)) {
                $addImage($url);
            }
        }

        // Extract just the URLs from the deduplicated array
        return array_values(array_map(fn ($item) => $item['url'], $imagesByHash));
    }

    /**
     * Recursively extract image URLs from JSON data.
     */
    protected function extractImagesFromJson(array $data, callable $addImage, int $depth = 0): void
    {
        // Prevent infinite recursion
        if ($depth > 15) {
            return;
        }

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Check if this is an image URL
                if (preg_match('/https?:\/\/[^"\'\s]+\.(?:jpg|jpeg|png|webp)/i', $value)) {
                    // Filter for likely listing images
                    if (preg_match('/(?:inmuebles24|img|cdn|avisos|pictures|multimedia)/i', $value)) {
                        $addImage($value);
                    }
                }
            } elseif (is_array($value)) {
                // Recurse into arrays and objects
                $this->extractImagesFromJson($value, $addImage, $depth + 1);
            }
        }
    }

    /**
     * Resolve property type from JS data, dataLayer, or extracted content.
     */
    protected function resolvePropertyType(array $jsData, array $dataLayerData, array $extracted): ?string
    {
        // First try from JS data property_type_id
        if (isset($jsData['property_type_id'])) {
            $types = $this->config->propertyTypes();
            $type = $types[$jsData['property_type_id']] ?? null;
            if ($type) {
                return $type;
            }
        }

        // Try from dataLayer property type text
        if (isset($dataLayerData['property_type_text'])) {
            $typeText = mb_strtolower($dataLayerData['property_type_text']);
            $textMappings = $this->config->propertyTypeTextMappings();

            // Try exact match first
            if (isset($textMappings[$typeText])) {
                return $textMappings[$typeText];
            }

            // Try partial match
            foreach ($textMappings as $keyword => $type) {
                if (str_contains($typeText, $keyword)) {
                    return $type;
                }
            }
        }

        // Try to detect from title
        $title = mb_strtolower($extracted['title'] ?? '');
        if (str_contains($title, 'departamento') || str_contains($title, 'depto')) {
            return 'apartment';
        }
        if (str_contains($title, 'casa')) {
            return 'house';
        }
        if (str_contains($title, 'terreno') || str_contains($title, 'lote')) {
            return 'land';
        }
        if (str_contains($title, 'oficina')) {
            return 'office';
        }
        if (str_contains($title, 'local') || str_contains($title, 'comercial')) {
            return 'commercial';
        }
        if (str_contains($title, 'bodega')) {
            return 'warehouse';
        }
        if (str_contains($title, 'nave')) {
            return 'industrial';
        }

        return null;
    }

    /**
     * Resolve publisher type from JS data or dataLayer.
     */
    protected function resolvePublisherType(array $jsData, array $dataLayerData = []): ?string
    {
        if (isset($jsData['publisher_type_id'])) {
            $types = $this->config->publisherTypes();
            $type = $types[$jsData['publisher_type_id']] ?? null;
            if ($type) {
                return $type;
            }
        }

        // Try from dataLayer
        if (isset($dataLayerData['publisher_type'])) {
            $typeText = mb_strtolower($dataLayerData['publisher_type']);
            if (str_contains($typeText, 'inmobiliaria') || str_contains($typeText, 'agencia')) {
                return 'agency';
            }
            if (str_contains($typeText, 'desarrollador')) {
                return 'developer';
            }
            if (str_contains($typeText, 'particular') || str_contains($typeText, 'dueño')) {
                return 'individual';
            }
        }

        return null;
    }

    /**
     * Build publisher URL.
     */
    protected function buildPublisherUrl(array $jsData): ?string
    {
        if (isset($jsData['publisher_url'])) {
            return 'https://www.inmuebles24.com'.$jsData['publisher_url'];
        }

        return null;
    }

    /**
     * Parse WhatsApp number from link or JS data.
     */
    protected function parseWhatsApp(?string $link, array $jsData): ?string
    {
        // Try from extracted link first
        if ($link && preg_match('/wa\.me\/(\d+)/', $link, $matches)) {
            return $this->normalizePhone($matches[1]);
        }

        // Fall back to JS data
        if (isset($jsData['whatsapp'])) {
            return $this->normalizePhone($jsData['whatsapp']);
        }

        return null;
    }

    /**
     * Normalize phone number to +52 format.
     */
    protected function normalizePhone(string $phone): ?string
    {
        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            // Local format: 3312345678
            return '+52'.$digits;
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            // Already has country code without +
            return '+'.$digits;
        } elseif (strlen($digits) === 13 && str_starts_with($digits, '521')) {
            // Has country code with mobile prefix
            return '+52'.substr($digits, 3);
        }

        return $phone;
    }

    /**
     * Extract external ID from URL.
     */
    protected function extractExternalId(string $url): ?string
    {
        if (preg_match($this->config->externalIdPattern(), $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Parse a number from text like "3 recámaras" → 3.
     */
    protected function parseNumber(mixed $text): ?int
    {
        if ($text === null) {
            return null;
        }

        // Handle array input (ZenRows may return array for single values)
        if (is_array($text)) {
            $text = $text[0] ?? null;
            if ($text === null) {
                return null;
            }
        }

        if (! is_string($text)) {
            return null;
        }

        if (preg_match('/(\d+)/', $text, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Clean description text.
     */
    protected function cleanDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        // Normalize whitespace
        $description = preg_replace('/\s+/', ' ', trim($description));

        // Remove "Leer descripción completa" and similar
        $description = preg_replace('/Leer descripci[óo]n completa/i', '', $description);

        return trim($description) ?: null;
    }

    /**
     * Clean extracted text.
     */
    protected function cleanText(mixed $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Handle array input (ZenRows may return arrays)
        if (is_array($text)) {
            $text = $text[0] ?? null;
            if ($text === null) {
                return null;
            }
        }

        if (! is_string($text)) {
            return null;
        }

        return trim(preg_replace('/\s+/', ' ', $text)) ?: null;
    }

    /**
     * Clean and upgrade image URL.
     */
    protected function cleanImageUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        // Skip icons/logos/placeholders
        if (preg_match('/icon|logo|placeholder/i', $url)) {
            return null;
        }

        // Upgrade to higher resolution
        $url = str_replace(['360x266', '720x532'], '1200x1200', $url);

        return $url;
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

        if (is_string($value) && ! empty($value)) {
            return [$value];
        }

        return [];
    }
}
