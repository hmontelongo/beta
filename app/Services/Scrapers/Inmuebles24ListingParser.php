<?php

namespace App\Services\Scrapers;

class Inmuebles24ListingParser
{
    protected Inmuebles24Config $config;

    public function __construct(Inmuebles24Config $config)
    {
        $this->config = $config;
    }

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

        // Extract features from icon-based elements
        $features = $this->extractFeatures($extracted);

        // Build operations array (price/rent info)
        $operations = $this->buildOperations($jsData, $rawHtml);

        // Parse location
        $location = $this->parseLocation($extracted, $jsData);

        // Parse description for additional data
        $description = $this->cleanDescription($extracted['description'] ?? '');
        $descriptionData = $this->parseDescription($description, $extracted['title'] ?? '');

        // Merge amenities from extraction and description
        $amenities = $this->mergeAmenities(
            $this->standardizeAmenities($this->toArray($extracted['amenities'] ?? [])),
            $descriptionData['amenities']
        );

        // Get images
        $images = $this->extractImages($extracted, $rawHtml);

        // Build the final data structure
        return [
            'external_id' => $this->extractExternalId($url) ?? $jsData['posting_id'] ?? null,
            'original_url' => $url,
            'title' => $this->cleanText($extracted['title'] ?? null),
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
            'property_type' => $this->resolvePropertyType($jsData, $extracted),
            'property_subtype' => $descriptionData['subtype'],

            // Location
            'address' => $location['address'],
            'colonia' => $location['colonia'],
            'city' => $location['city'],
            'state' => $location['state'],
            'latitude' => $jsData['latitude'] ?? null,
            'longitude' => $jsData['longitude'] ?? null,

            // Publisher
            'publisher_id' => $jsData['publisher_id'] ?? null,
            'publisher_name' => $jsData['publisher_name'] ?? $this->cleanText($extracted['publisher_name'] ?? null),
            'publisher_type' => $this->resolvePublisherType($jsData),
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
     * Extract features from icon-based elements.
     *
     * @return array<string, int|null>
     */
    protected function extractFeatures(array $extracted): array
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
     * Build operations array from JavaScript data.
     *
     * @return array<array{type: string, price: int, currency: string, maintenance_fee: int|null}>
     */
    protected function buildOperations(array $jsData, string $html): array
    {
        $operations = [];

        if (isset($jsData['price']) && isset($jsData['operation_type_id'])) {
            $operationTypes = $this->config->operationTypes();
            $currencyTypes = $this->config->currencyTypes();

            $operation = [
                'type' => $operationTypes[$jsData['operation_type_id']] ?? 'sale',
                'price' => (int) $jsData['price'],
                'currency' => $currencyTypes[$jsData['currency_id'] ?? 10] ?? 'MXN',
                'maintenance_fee' => $this->extractMaintenanceFee($html),
            ];

            $operations[] = $operation;
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
     * Parse location from extracted data and JS variables.
     *
     * @return array{address: string|null, colonia: string|null, city: string|null, state: string|null}
     */
    protected function parseLocation(array $extracted, array $jsData): array
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
        $images = [];
        $seen = [];

        // Try gallery images first
        foreach ($this->toArray($extracted['gallery_images'] ?? []) as $url) {
            $url = $this->cleanImageUrl($url);
            if ($url && ! isset($seen[$url])) {
                $images[] = $url;
                $seen[$url] = true;
            }
        }

        // Try carousel images
        foreach ($this->toArray($extracted['carousel_images'] ?? []) as $url) {
            $url = $this->cleanImageUrl($url);
            if ($url && ! isset($seen[$url])) {
                $images[] = $url;
                $seen[$url] = true;
            }
        }

        // Fallback: extract from HTML if no images found
        if (empty($images)) {
            preg_match_all('/url1200x1200["\']\s*[:=]\s*["\']([^"\']+)["\']/i', $rawHtml, $matches);
            foreach ($matches[1] ?? [] as $url) {
                if (! isset($seen[$url])) {
                    $images[] = $url;
                    $seen[$url] = true;
                }
            }
        }

        return $images;
    }

    /**
     * Resolve property type from JS data or extracted content.
     */
    protected function resolvePropertyType(array $jsData, array $extracted): ?string
    {
        if (isset($jsData['property_type_id'])) {
            $types = $this->config->propertyTypes();

            return $types[$jsData['property_type_id']] ?? null;
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

        return null;
    }

    /**
     * Resolve publisher type from JS data.
     */
    protected function resolvePublisherType(array $jsData): ?string
    {
        if (isset($jsData['publisher_type_id'])) {
            $types = $this->config->publisherTypes();

            return $types[$jsData['publisher_type_id']] ?? null;
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
    protected function parseNumber(?string $text): ?int
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
