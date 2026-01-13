<?php

namespace App\Services\Scrapers;

use App\Contracts\ListingParserInterface;
use App\Contracts\ScraperConfigInterface;

class VivanunciosListingParser implements ListingParserInterface
{
    public function __construct(protected ScraperConfigInterface $config) {}

    /**
     * Parse listing data from ZenRows CSS-extracted data + raw HTML.
     * Vivanuncios has JSON-LD structured data which is the primary source.
     *
     * @param  array<string, mixed>  $extracted  Data from ZenRows css_extractor
     * @param  string  $rawHtml  Raw HTML for JSON-LD and JS variable extraction
     * @param  string  $url  Original listing URL
     * @return array<string, mixed>
     */
    public function parse(array $extracted, string $rawHtml, string $url): array
    {
        // Primary source: JSON-LD structured data
        $jsonLd = $this->extractJsonLd($rawHtml);

        // Secondary: JavaScript variables
        $jsData = $this->extractJavaScriptVars($rawHtml);

        // Secondary: dataLayer
        $dataLayerData = $this->extractDataLayer($rawHtml);

        // Parse description (check for maintenance included)
        // Handle array descriptions (ZenRows may return arrays for multiple matches)
        $rawDescription = $jsonLd['description'] ?? $extracted['description'] ?? '';
        if (is_array($rawDescription)) {
            $rawDescription = implode(' ', array_filter($rawDescription));
        }
        $description = $this->cleanDescription($rawDescription);

        // Extract features (from JSON-LD or HTML)
        $features = $this->extractFeatures($jsonLd, $extracted, $dataLayerData);

        // Build operations (price/rent info)
        $operations = $this->buildOperations($jsonLd, $jsData, $dataLayerData, $rawHtml, $description);

        // Parse location
        $location = $this->parseLocation($jsonLd, $extracted, $dataLayerData);

        // Get images
        $images = $this->extractImages($jsonLd, $extracted, $rawHtml);

        // Extract coordinates
        $coordinates = $this->extractCoordinates($jsonLd, $rawHtml);

        // Build the final data structure
        return [
            'external_id' => $this->config->extractExternalId($url) ?? $jsData['posting_id'] ?? null,
            'original_url' => $url,
            'title' => $this->cleanText($jsonLd['name'] ?? $extracted['title'] ?? null),
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
            'property_type' => $this->resolvePropertyType($jsonLd, $jsData, $dataLayerData, $extracted),
            'property_subtype' => $this->detectSubtype($jsonLd['name'] ?? $extracted['title'] ?? ''),

            // Location
            'address' => $location['address'],
            'colonia' => $location['colonia'],
            'city' => $location['city'],
            'state' => $location['state'],
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],

            // Publisher
            'publisher_id' => $jsData['publisher_id'] ?? null,
            'publisher_name' => $this->cleanText($extracted['publisher_name'] ?? null),
            'publisher_type' => null, // Vivanuncios doesn't expose this clearly
            'publisher_url' => null,
            'publisher_logo' => null,
            'whatsapp' => $this->parsePhone($jsonLd['telephone'] ?? $extracted['phone_link'] ?? null),

            // Images
            'images' => $images,

            // Amenities (from description)
            'amenities' => $this->extractAmenities($description),

            // External codes
            'external_codes' => $this->extractExternalCodes($rawHtml),

            // Data quality indicators
            'data_quality' => [
                'has_json_ld' => ! empty($jsonLd),
                'has_conflicts' => false,
            ],

            // Platform metadata
            'platform_metadata' => [
                'json_ld' => $jsonLd,
                'dataLayer' => $dataLayerData,
            ],
        ];
    }

    /**
     * Extract JSON-LD structured data from HTML.
     * This is the most reliable data source for Vivanuncios.
     *
     * @return array<string, mixed>
     */
    protected function extractJsonLd(string $html): array
    {
        // Find all JSON-LD scripts
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.+?)<\/script>/si', $html, $matches);

        foreach ($matches[1] ?? [] as $jsonStr) {
            $data = @json_decode(trim($jsonStr), true);
            if (! $data) {
                continue;
            }

            // Look for property types (Apartment, House, SingleFamilyResidence, etc.)
            $propertyTypes = ['Apartment', 'House', 'SingleFamilyResidence', 'RealEstateListing', 'Product', 'Residence'];

            $type = $data['@type'] ?? null;
            if ($type && in_array($type, $propertyTypes)) {
                return $data;
            }

            // Check @graph structure
            if (isset($data['@graph'])) {
                foreach ($data['@graph'] as $item) {
                    $type = $item['@type'] ?? null;
                    if ($type && in_array($type, $propertyTypes)) {
                        return $item;
                    }
                }
            }
        }

        return [];
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
     *
     * @return array<string, mixed>
     */
    protected function extractDataLayer(string $html): array
    {
        $data = [];

        foreach ($this->config->dataLayerPatterns() as $key => $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $value = $matches[1];
                if (is_numeric($value)) {
                    $value = str_contains($value, '.') ? (float) $value : (int) $value;
                }
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Extract features from JSON-LD and HTML.
     *
     * @return array<string, int|null>
     */
    protected function extractFeatures(array $jsonLd, array $extracted, array $dataLayerData): array
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

        // Primary: JSON-LD
        if (! empty($jsonLd)) {
            $features['bedrooms'] = $this->parseIntOrNull($jsonLd['numberOfBedrooms'] ?? $jsonLd['numberOfRooms'] ?? null);
            $features['bathrooms'] = $this->parseIntOrNull($jsonLd['numberOfBathroomsTotal'] ?? null);

            // Floor size from JSON-LD
            if (isset($jsonLd['floorSize'])) {
                $floorSize = $jsonLd['floorSize'];
                if (is_array($floorSize) && isset($floorSize['value'])) {
                    $features['built_size_m2'] = $this->parseIntOrNull($floorSize['value']);
                } elseif (is_numeric($floorSize)) {
                    $features['built_size_m2'] = (int) $floorSize;
                }
            }
        }

        // Secondary: CSS extracted
        if ($features['bedrooms'] === null) {
            $features['bedrooms'] = $this->parseNumber($extracted['bedrooms_text'] ?? null);
        }
        if ($features['bathrooms'] === null) {
            $features['bathrooms'] = $this->parseNumber($extracted['bathrooms_text'] ?? null);
        }
        if ($features['parking_spots'] === null) {
            $features['parking_spots'] = $this->parseNumber($extracted['parking_text'] ?? null);
        }
        if ($features['built_size_m2'] === null) {
            $features['built_size_m2'] = $this->parseNumber($extracted['area_text'] ?? null);
        }

        // Tertiary: Feature items text parsing
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

            if (preg_match('/rec[aá]mara|dormitorio|\brec\.?\b/u', $itemLower) && $features['bedrooms'] === null) {
                $features['bedrooms'] = $number;
            } elseif (preg_match('/ba[ñn]o/u', $itemLower) && $features['bathrooms'] === null) {
                $features['bathrooms'] = $number;
            } elseif (preg_match('/estac|cochera|parking/u', $itemLower) && $features['parking_spots'] === null) {
                $features['parking_spots'] = $number;
            } elseif (preg_match('/m[²2]\s*(lote|terreno|total)/u', $itemLower) && $features['lot_size_m2'] === null) {
                $features['lot_size_m2'] = $number;
            } elseif (preg_match('/m[²2]\s*(constr|cubierta)/u', $itemLower) && $features['built_size_m2'] === null) {
                $features['built_size_m2'] = $number;
            } elseif (preg_match('/m[²2]/u', $itemLower) && $features['built_size_m2'] === null) {
                // Generic m² without qualifier
                $features['built_size_m2'] = $number;
            }
        }

        return $features;
    }

    /**
     * Build operations array from available data.
     *
     * @return array<array{type: string, price: int, currency: string, maintenance_fee: int|null}>
     */
    protected function buildOperations(array $jsonLd, array $jsData, array $dataLayerData, string $html, ?string $description): array
    {
        $operations = [];
        $operationTypes = $this->config->operationTypes();
        $currencyTypes = $this->config->currencyTypes();
        $maintenanceFee = $this->extractMaintenanceFee($html, $description);

        // Try from JS data first
        if (isset($jsData['price']) && isset($jsData['operation_type_id'])) {
            $operations[] = [
                'type' => $operationTypes[$jsData['operation_type_id']] ?? 'rent',
                'price' => (int) $jsData['price'],
                'currency' => $currencyTypes[$jsData['currency_id'] ?? 10] ?? 'MXN',
                'maintenance_fee' => $maintenanceFee,
            ];
        }

        // Try from dataLayer
        if (empty($operations)) {
            $rentPrice = $this->parsePriceText($dataLayerData['rent_price'] ?? null);
            $salePrice = $this->parsePriceText($dataLayerData['sale_price'] ?? null);

            if ($rentPrice) {
                $operations[] = [
                    'type' => 'rent',
                    'price' => $rentPrice['amount'],
                    'currency' => $rentPrice['currency'],
                    'maintenance_fee' => $maintenanceFee,
                ];
            }

            if ($salePrice) {
                $operations[] = [
                    'type' => 'sale',
                    'price' => $salePrice['amount'],
                    'currency' => $salePrice['currency'],
                    'maintenance_fee' => null,
                ];
            }
        }

        // Fallback: extract from HTML
        if (empty($operations)) {
            $operations = $this->extractOperationsFromHtml($html, $maintenanceFee);
        }

        return $operations;
    }

    /**
     * Parse price from text like "MN 2000" or "$8,500".
     *
     * @return array{amount: int, currency: string}|null
     */
    protected function parsePriceText(?string $text): ?array
    {
        if (! $text) {
            return null;
        }

        // Format: "MN 2000" or "USD 150000"
        if (preg_match('/^(MN|MXN|USD)\s*([\d,]+)/i', $text, $matches)) {
            return [
                'amount' => (int) str_replace(',', '', $matches[2]),
                'currency' => strtoupper($matches[1]) === 'MN' ? 'MXN' : strtoupper($matches[1]),
            ];
        }

        // Format: "$8,500"
        if (preg_match('/\$\s*([\d,]+)/', $text, $matches)) {
            return [
                'amount' => (int) str_replace(',', '', $matches[1]),
                'currency' => 'MXN',
            ];
        }

        return null;
    }

    /**
     * Extract operations from HTML.
     *
     * @return array<array{type: string, price: int, currency: string, maintenance_fee: int|null}>
     */
    protected function extractOperationsFromHtml(string $html, ?int $maintenanceFee): array
    {
        $operations = [];

        // Look for rent prices
        if (preg_match('/(?:renta|alquiler)[^\d]*\$?\s*([\d,]+)\s*(?:MXN|MN)?/i', $html, $matches)) {
            $operations[] = [
                'type' => 'rent',
                'price' => (int) str_replace(',', '', $matches[1]),
                'currency' => 'MXN',
                'maintenance_fee' => $maintenanceFee,
            ];
        }

        // Look for sale prices
        if (preg_match('/(?:venta)[^\d]*\$?\s*([\d,]+)\s*(?:MXN|MN)?/i', $html, $matches)) {
            $operations[] = [
                'type' => 'sale',
                'price' => (int) str_replace(',', '', $matches[1]),
                'currency' => 'MXN',
                'maintenance_fee' => null,
            ];
        }

        return $operations;
    }

    /**
     * Extract maintenance fee from HTML and description.
     */
    protected function extractMaintenanceFee(string $html, ?string $description): ?int
    {
        $searchText = $html.($description ? ' '.$description : '');

        // Check if maintenance is included
        if (preg_match('/mantenimiento\s+incluid[oa]|incluy[ea]\s+mantenimiento/i', $searchText)) {
            return null;
        }

        // Look for explicit maintenance fee
        $patterns = [
            '/Mantenimiento\s*[:]\s*(?:MN|USD|\$)?\s*([\d,]+)/i',
            '/MN\s*([\d,]+)\s*(?:de\s+)?Mantenimiento(?!\s+incluid)/i',
            '/\+\s*\$?\s*([\d,]+)\s*(?:de\s+)?mant/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $searchText, $matches)) {
                return (int) str_replace(',', '', $matches[1]);
            }
        }

        return null;
    }

    /**
     * Parse location from JSON-LD and extracted data.
     *
     * @return array{address: string|null, colonia: string|null, city: string|null, state: string|null}
     */
    protected function parseLocation(array $jsonLd, array $extracted, array $dataLayerData): array
    {
        $location = [
            'address' => null,
            'colonia' => null,
            'city' => null,
            'state' => null,
        ];

        // Primary: JSON-LD address
        if (isset($jsonLd['address'])) {
            $address = $jsonLd['address'];
            if (is_array($address)) {
                $location['address'] = $address['streetAddress'] ?? null;
                $location['colonia'] = $address['addressRegion'] ?? null;

                // Parse locality (format: "City, State, Country")
                if (isset($address['addressLocality'])) {
                    $parts = array_map('trim', explode(',', $address['addressLocality']));
                    $location['city'] = $parts[0] ?? null;
                    if (count($parts) >= 2) {
                        $location['state'] = $parts[1] ?? null;
                    }
                }
            }
        }

        // Secondary: location header
        if (! $location['address']) {
            $header = $this->cleanText($extracted['location_header'] ?? null);
            if ($header) {
                $parts = array_map('trim', explode(',', $header));
                $location['address'] = $parts[0] ?? null;
                if (count($parts) >= 2) {
                    $location['colonia'] = $parts[1] ?? null;
                }
                if (count($parts) >= 3) {
                    $location['city'] = $parts[2] ?? null;
                }
                if (count($parts) >= 4) {
                    $location['state'] = $parts[3] ?? null;
                }
            }
        }

        // Tertiary: dataLayer
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
     * Extract coordinates from JSON-LD or HTML.
     *
     * @return array{latitude: float|null, longitude: float|null}
     */
    protected function extractCoordinates(array $jsonLd, string $html): array
    {
        // Try JSON-LD geo
        if (isset($jsonLd['geo'])) {
            $geo = $jsonLd['geo'];
            if (isset($geo['latitude']) && isset($geo['longitude'])) {
                return [
                    'latitude' => (float) $geo['latitude'],
                    'longitude' => (float) $geo['longitude'],
                ];
            }
        }

        // Try various HTML patterns
        $patterns = [
            '/"latitude"\s*:\s*([-\d.]+)[^}]*"longitude"\s*:\s*([-\d.]+)/',
            '/"lat"\s*:\s*([-\d.]+)[^}]*"lng"\s*:\s*([-\d.]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $lat = (float) $matches[1];
                $lng = (float) $matches[2];

                // Validate Mexico coordinates
                if ($lat >= 14 && $lat <= 33 && $lng >= -118 && $lng <= -86) {
                    return ['latitude' => $lat, 'longitude' => $lng];
                }
            }
        }

        return ['latitude' => null, 'longitude' => null];
    }

    /**
     * Extract images from JSON-LD and HTML.
     *
     * @return array<string>
     */
    protected function extractImages(array $jsonLd, array $extracted, string $html): array
    {
        $images = [];

        // Primary: JSON-LD image(s)
        if (isset($jsonLd['image'])) {
            $jsonImages = is_array($jsonLd['image']) ? $jsonLd['image'] : [$jsonLd['image']];
            foreach ($jsonImages as $img) {
                $url = is_array($img) ? ($img['url'] ?? $img['contentUrl'] ?? null) : $img;
                if ($url && is_string($url)) {
                    $images[] = $this->cleanImageUrl($url);
                }
            }
        }

        // Secondary: CSS extracted
        foreach ($this->toArray($extracted['gallery_images'] ?? []) as $url) {
            $cleaned = $this->cleanImageUrl($url);
            if ($cleaned && ! in_array($cleaned, $images)) {
                $images[] = $cleaned;
            }
        }

        // Tertiary: Extract from HTML - JS objects
        preg_match_all('/"(?:url|src|image)"\s*:\s*"(https?:\/\/[^"]+\.(?:jpg|jpeg|png|webp)[^"]*)"/i', $html, $htmlMatches);
        foreach ($htmlMatches[1] ?? [] as $url) {
            if (preg_match('/(?:naventcdn|vivanuncios|img)/i', $url)) {
                $cleaned = $this->cleanImageUrl($url);
                if ($cleaned && ! in_array($cleaned, $images)) {
                    $images[] = $cleaned;
                }
            }
        }

        // Quaternary: Extract all naventcdn property images via regex
        // This catches images from img tags: src="https://img10.naventcdn.com/avisos/..."
        preg_match_all('/https:\/\/img10\.naventcdn\.com\/avisos\/[^\s"\']+\.jpg/i', $html, $naventMatches);
        foreach ($naventMatches[0] ?? [] as $url) {
            $cleaned = $this->cleanImageUrl($url);
            if ($cleaned && ! in_array($cleaned, $images)) {
                $images[] = $cleaned;
            }
        }

        return array_filter($images);
    }

    /**
     * Extract amenities from description.
     *
     * @return array<string>
     */
    protected function extractAmenities(?string $description): array
    {
        if (! $description) {
            return [];
        }

        $amenities = [];
        $textUpper = mb_strtoupper($description);

        $patterns = [
            '/ALBERCA|PISCINA/' => 'pool',
            '/GIMNASIO|GYM\b/' => 'gym',
            '/SEGURIDAD\s*24|VIGILANCIA/' => 'security_24h',
            '/ELEVADOR|ASCENSOR/' => 'elevator',
            '/ROOF\s*GARDEN/' => 'rooftop',
            '/TERRAZA/' => 'terrace',
            '/BALCON/' => 'balcony',
            '/PET\s*FRIENDLY|MASCOTAS/' => 'pet_friendly',
            '/\bAMUEBLADO\b/' => 'furnished',
        ];

        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $textUpper)) {
                $amenities[] = $name;
            }
        }

        return array_unique($amenities);
    }

    /**
     * Extract external codes from HTML.
     *
     * @return array<string, string>
     */
    protected function extractExternalCodes(string $html): array
    {
        $codes = [];

        // Vivanuncios code
        if (preg_match('/C[óo]d\.?\s*Vivanuncios[:\s]+(\d+)/i', $html, $matches)) {
            $codes['vivanuncios'] = $matches[1];
        }

        // Advertiser code
        if (preg_match('/C[óo]d\.?\s*(?:del\s+)?anunciante[:\s]+([A-Z0-9-]+)/i', $html, $matches)) {
            $codes['advertiser'] = $matches[1];
        }

        // EasyBroker
        if (preg_match('/EB-[A-Z0-9]{6,}/i', $html, $matches)) {
            $codes['easybroker'] = $matches[0];
        }

        return array_filter($codes);
    }

    /**
     * Resolve property type from available data.
     */
    protected function resolvePropertyType(array $jsonLd, array $jsData, array $dataLayerData, array $extracted): ?string
    {
        // Primary: JSON-LD @type
        if (isset($jsonLd['@type'])) {
            $type = mb_strtolower($jsonLd['@type']);
            $mappings = $this->config->propertyTypeTextMappings();

            if (isset($mappings[$type])) {
                return $mappings[$type];
            }

            // Common JSON-LD types
            if (str_contains($type, 'apartment')) {
                return 'apartment';
            }
            if (str_contains($type, 'house') || str_contains($type, 'singlefamily')) {
                return 'house';
            }
        }

        // Secondary: JS data
        if (isset($jsData['property_type_id'])) {
            $types = $this->config->propertyTypes();

            return $types[$jsData['property_type_id']] ?? null;
        }

        // Tertiary: Title detection
        $title = mb_strtolower($extracted['title'] ?? '');
        if (str_contains($title, 'departamento') || str_contains($title, 'depto')) {
            return 'apartment';
        }
        if (str_contains($title, 'casa')) {
            return 'house';
        }
        if (str_contains($title, 'terreno')) {
            return 'land';
        }
        if (str_contains($title, 'oficina')) {
            return 'office';
        }

        return null;
    }

    /**
     * Detect property subtype from title.
     */
    protected function detectSubtype(?string $title): ?string
    {
        if (! $title) {
            return null;
        }

        $titleUpper = mb_strtoupper($title);

        if (preg_match('/\bPH\b|PENTHOUSE/i', $titleUpper)) {
            return 'penthouse';
        }
        if (preg_match('/PLANTA\s+BAJA|\bPB\b/i', $titleUpper)) {
            return 'ground_floor';
        }
        if (preg_match('/\bLOFT\b/i', $titleUpper)) {
            return 'loft';
        }
        if (preg_match('/DUPLEX|D[ÚU]PLEX/i', $titleUpper)) {
            return 'duplex';
        }
        if (preg_match('/ESTUDIO/i', $titleUpper)) {
            return 'studio';
        }

        return null;
    }

    /**
     * Parse phone number from various formats.
     */
    protected function parsePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Extract from tel: link
        if (str_starts_with($phone, 'tel:')) {
            $phone = substr($phone, 4);
        }

        // Remove non-digits
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            return '+52'.$digits;
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            return '+'.$digits;
        }

        return $phone ?: null;
    }

    /**
     * Parse a number from text.
     */
    protected function parseNumber(mixed $text): ?int
    {
        if ($text === null) {
            return null;
        }

        if (is_array($text)) {
            $text = $text[0] ?? null;
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
     * Parse int or return null.
     */
    protected function parseIntOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Clean description text.
     */
    protected function cleanDescription(mixed $description): ?string
    {
        if ($description === null) {
            return null;
        }

        // Handle array input
        if (is_array($description)) {
            $description = implode(' ', array_filter($description));
        }

        if (! is_string($description)) {
            return null;
        }

        $description = preg_replace('/\s+/', ' ', trim($description));

        return $description ?: null;
    }

    /**
     * Clean extracted text.
     */
    protected function cleanText(mixed $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (is_array($text)) {
            $text = $text[0] ?? null;
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

        // Skip icons/logos/placeholders/badges
        if (preg_match('/icon|logo|placeholder|badge/i', $url)) {
            return null;
        }

        // Skip UI elements from /ficha/ path (badges, checkmarks, etc.)
        if (str_contains($url, '/ficha/')) {
            return null;
        }

        // Remove query parameters for cleaner URLs and better deduplication
        $url = preg_replace('/\?.*$/', '', $url);

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
