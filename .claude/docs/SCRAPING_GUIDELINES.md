# Scraping Guidelines for New Platforms

This guide covers implementing scrapers for new real estate platforms. The architecture uses ZenRows API for browser rendering with platform-specific parsers in Laravel.

## Architecture Overview

```
Search URL → ZenRowsClient.fetchSearchPage() → SearchParser.parse()
    ↓
Discovered listing URLs + preview data
    ↓
For each listing URL:
    ZenRowsClient.fetchListingPage() → CSS extraction
    ZenRowsClient.fetchRawHtml() → Raw HTML for JS variable extraction
    ↓
    ListingParser.parse() → Combines both sources
    ↓
    Listing record created
```

## Files Required for Each Platform

Each new platform requires **4 files** + **2 test files**:

### 1. Config Class
**Location:** `app/Services/Scrapers/{Platform}Config.php`

Implements `ScraperConfigInterface`:

```php
class NewPlatformConfig implements ScraperConfigInterface
{
    // CSS selectors for search pages (ZenRows css_extractor)
    public function searchExtractor(): array;

    // CSS selectors for listing pages
    public function listingExtractor(): array;

    // Regex patterns for JS variable extraction
    public function jsPatterns(): array;

    // Regex for window.dataLayer extraction
    public function dataLayerPatterns(): array;

    // Property type mappings (Spanish → standard English)
    public function propertyTypes(): array;

    // Operation type mappings (sale/rent)
    public function operationTypes(): array;

    // Currency mappings
    public function currencyTypes(): array;

    // Amenity name mappings (Spanish → standard English)
    public function amenityMappings(): array;

    // Property subtype patterns (regex → subtype)
    public function subtypePatterns(): array;

    // URL pagination logic
    public function paginateUrl(string $url, int $page): string;

    // Extract external ID from URL
    public function extractExternalId(string $url): ?string;

    // Platform-specific ZenRows API options (proxy_country, custom_headers, etc.)
    public function zenrowsOptions(): array;
}
```

### 2. Search Parser
**Location:** `app/Services/Scrapers/{Platform}SearchParser.php`

Implements `SearchParserInterface`:

```php
class NewPlatformSearchParser implements SearchParserInterface
{
    public function parse(array $extracted, string $baseUrl): array
    {
        return [
            'total_results' => int|null,
            'total_pages' => int|null,
            'listings' => [
                [
                    'url' => string,          // Clean listing URL
                    'external_id' => string|null,
                    'preview' => [
                        'title' => string|null,
                        'price' => string|null,
                        'location' => string|null,
                        'image' => string|null,
                    ],
                ],
            ],
        ];
    }
}
```

### 3. Listing Parser
**Location:** `app/Services/Scrapers/{Platform}ListingParser.php`

Implements `ListingParserInterface`:

```php
class NewPlatformListingParser implements ListingParserInterface
{
    public function parse(array $extracted, string $rawHtml, string $url): array
    {
        return [
            // Identification
            'external_id' => string,
            'original_url' => string,

            // Content
            'title' => string|null,
            'description' => string|null,

            // Operations (sale/rent with prices)
            'operations' => [
                ['type' => 'sale|rent', 'price' => int, 'currency' => string, 'maintenance_fee' => int|null],
            ],

            // Features
            'bedrooms' => int|null,
            'bathrooms' => float|null,
            'half_bathrooms' => float|null,
            'parking_spots' => int|null,
            'lot_size_m2' => float|null,
            'built_size_m2' => float|null,
            'age_years' => int|null,
            'property_type' => string,
            'property_subtype' => string|null,

            // Location
            'address' => string|null,
            'colonia' => string|null,
            'city' => string|null,
            'state' => string|null,
            'postal_code' => string|null,
            'latitude' => float|null,
            'longitude' => float|null,

            // Publisher
            'publisher_name' => string|null,
            'publisher_type' => string|null,

            // Media & extras
            'images' => array<string>,
            'amenities' => array<string>,
            'external_codes' => array|null,
            'data_quality' => array|null,
            'platform_metadata' => array|null,
        ];
    }
}
```

### 4. Factory Registration
**Location:** `app/Services/Scrapers/ScraperFactory.php`

Add the platform to all three factory methods:

```php
public function createConfig(Platform $platform): ScraperConfigInterface
{
    return match ($identifier) {
        // ...existing platforms...
        'newplatform' => new NewPlatformConfig,
        default => throw new InvalidArgumentException(...),
    };
}

public function createSearchParser(Platform $platform, ?ScraperConfigInterface $config = null): SearchParserInterface
{
    return match ($identifier) {
        // ...existing platforms...
        'newplatform' => new NewPlatformSearchParser($config),
        default => throw new InvalidArgumentException(...),
    };
}

public function createListingParser(Platform $platform, ?ScraperConfigInterface $config = null): ListingParserInterface
{
    return match ($identifier) {
        // ...existing platforms...
        'newplatform' => new NewPlatformListingParser($config),
        default => throw new InvalidArgumentException(...),
    };
}

public function detectPlatformFromUrl(string $url): Platform
{
    $slugOrName = match (true) {
        // ...existing platforms...
        str_contains($host, 'newplatform.com') => 'newplatform',
        default => null,
    };
}
```

### 5. Tests
**Locations:**
- `tests/Unit/Scrapers/{Platform}SearchParserTest.php`
- `tests/Unit/Scrapers/{Platform}ListingParserTest.php`

See existing tests for patterns.

---

## Implementation Workflow

### Step 1: Analyze Search Page
Use Playwright MCP tools to inspect the search results page:

1. Navigate to a search URL
2. Take a snapshot to identify:
   - Listing card selectors (links, titles, prices, images)
   - Pagination controls (URL pattern, page parameter)
   - Total results indicator (usually in H1 or header)

**Key selectors to find:**
- `urls`: Links to individual listings
- `titles`: Listing titles/headlines
- `prices`: Price displays
- `locations`: Location/neighborhood text
- `images`: Thumbnail images
- `h1_title`: Total results count (e.g., "2,163 Casas en Renta")
- `pagination_links`: Page navigation links

### Step 2: Analyze Listing Page
Navigate to an individual listing and identify:

1. **Structured Data** (preferred sources):
   - JSON-LD (`<script type="application/ld+json">`)
   - `__NEXT_DATA__` (Next.js sites)
   - `window.dataLayer` (Google Tag Manager)

2. **Meta Tags:**
   - Coordinates: `ICBM`, `geo.position`, `geo.placename`
   - Description: `meta[name="description"]`

3. **HTML Patterns:**
   - Feature elements (bedrooms, bathrooms, area, parking)
   - Image gallery/carousel
   - Publisher/agent information
   - Amenities lists

### Step 3: Understand URL Patterns
Document:
- **Pagination:** How pages are numbered (`?pagina=N`, `?page=N`, `/page/N`)
- **External ID:** Where the unique ID appears in URLs (end, middle, query param)
- **URL cleaning:** What fragments/params to remove

### Step 4: Create Implementation
Follow this order:
1. Config class (selectors, mappings, patterns)
2. Search parser (URL extraction, deduplication, pagination)
3. Listing parser (multi-source extraction with fallbacks)
4. Factory registration
5. Tests

---

## Key Implementation Patterns

### Multi-Source Data Extraction
Always implement 2-4 fallback sources for each field:

```php
protected function extractPrice(array $jsonLd, array $extracted, string $rawHtml): ?int
{
    // Source 1: JSON-LD
    foreach ($jsonLd as $item) {
        if (isset($item['offers']['price'])) {
            return (int) $item['offers']['price'];
        }
    }

    // Source 2: CSS extraction
    if (!empty($extracted['price'])) {
        return $this->parsePrice($extracted['price']);
    }

    // Source 3: Regex from HTML
    if (preg_match('/\$([0-9,]+)/', $rawHtml, $match)) {
        return (int) str_replace(',', '', $match[1]);
    }

    return null;
}
```

### Image Deduplication
CDNs often serve the same image at different resolutions. Deduplicate by extracting a unique identifier:

```php
protected function extractImages(string $rawHtml): array
{
    $images = [];
    $seenIds = [];

    foreach ($allImageUrls as $url) {
        // Extract image ID (UUID, numeric ID, hash)
        if (preg_match('/([a-f0-9-]{36})\.(jpg|jpeg|png|webp)/i', $url, $match)) {
            $imageId = $match[1];
            if (isset($seenIds[$imageId])) {
                // Keep higher resolution version
                continue;
            }
            $seenIds[$imageId] = $url;
        }
        $images[] = $this->upgradeResolution($url);
    }

    return $images;
}
```

### Coordinate Validation
Always validate coordinates are within Mexico bounds:

```php
protected const MEXICO_LAT_MIN = 14;
protected const MEXICO_LAT_MAX = 33;
protected const MEXICO_LNG_MIN = -118;
protected const MEXICO_LNG_MAX = -86;

protected function isValidMexicoCoordinate(float $lat, float $lng): bool
{
    return $lat >= self::MEXICO_LAT_MIN
        && $lat <= self::MEXICO_LAT_MAX
        && $lng >= self::MEXICO_LNG_MIN
        && $lng <= self::MEXICO_LNG_MAX;
}
```

### Amenity Standardization
Map Spanish amenity names to English standard names:

```php
public function amenityMappings(): array
{
    return [
        'alberca' => 'pool',
        'piscina' => 'pool',
        'gimnasio' => 'gym',
        'estacionamiento' => 'parking',
        'seguridad 24h' => 'security_24h',
        'aire acondicionado' => 'ac',
        'jardín' => 'garden',
        'roof garden' => 'rooftop',
        'área de juegos' => 'playground',
        'elevador' => 'elevator',
        'bodega' => 'storage',
        'cuarto de servicio' => 'service_room',
    ];
}
```

### UTF-8 Regex Patterns
When using character classes with accented characters, **always add the `/u` flag**:

```php
// WRONG - will not match UTF-8 properly
preg_match('/REC[ÁA]MARAS?\s*(\d+)/i', $html, $match);

// CORRECT - with Unicode flag
preg_match('/REC[ÁA]MARAS?\s*(\d+)/iu', $html, $match);
```

### URL Cleaning
Remove tracking parameters and fragments:

```php
protected function cleanListingUrl(string $url): string
{
    // Remove hash fragment
    $url = explode('#', $url)[0];

    // Remove query string (or selectively remove tracking params)
    $url = explode('?', $url)[0];

    return $url;
}
```

---

## Standard Property Types

Use these standardized property type values:

| Spanish Term | Standard Value |
|--------------|----------------|
| casa | house |
| departamento | apartment |
| terreno | land |
| local | commercial |
| oficina | office |
| bodega | warehouse |
| edificio | building |

## Standard Subtypes

| Pattern | Subtype |
|---------|---------|
| penthouse | penthouse |
| loft | loft |
| estudio | studio |
| townhouse | townhouse |
| duplex | duplex |

---

## Testing Guidelines

### Search Parser Tests
Test these scenarios:
- Multiple listings parsing
- URL hash/query param removal
- External ID extraction
- Title cleaning (removing ID suffixes)
- URL deduplication
- Total results parsing from various formats
- Pagination page count parsing
- Empty/malformed input handling
- Non-listing URL filtering (navigation, login pages)
- Single value handling (ZenRows sometimes returns strings vs arrays)

### Listing Parser Tests
Test these scenarios:
- Full JSON-LD parsing
- Coordinate extraction from meta tags
- Coordinate validation (reject non-Mexico coords)
- Feature extraction from HTML patterns
- External ID extraction (URL and HTML fallback)
- Image deduplication by UUID
- Property type resolution (JSON-LD and URL fallback)
- Property subtype detection
- Amenity standardization
- Location from breadcrumbs
- Description from meta tag fallback
- Empty HTML handling
- Complete data structure verification

---

## Existing Platform References

Study these implementations for patterns:

| Platform | Config | Search Parser | Listing Parser |
|----------|--------|---------------|----------------|
| Inmuebles24 | `Inmuebles24Config` | `Inmuebles24SearchParser` | `Inmuebles24ListingParser` |
| Vivanuncios | `VivanunciosConfig` | `VivanunciosSearchParser` | `VivanunciosListingParser` |
| Propiedades | `PropiedadesConfig` | `PropiedadesSearchParser` | `PropiedadesListingParser` |

---

## Common Data Sources by Platform Type

### Next.js Sites (check for `__NEXT_DATA__`)
- Primary: `__NEXT_DATA__` JSON in script tag
- Contains pre-rendered page props with listing data
- Usually has complete structured data

### Schema.org Sites (check for JSON-LD)
- Primary: `<script type="application/ld+json">`
- Types: `RealEstateListing`, `Offer`, `House`, `Apartment`
- Often has `amenityFeature` array

### Google Tag Manager Sites
- Check: `window.dataLayer` push events
- Contains analytics data often including listing details

### Traditional Sites
- Primary: CSS selectors and HTML patterns
- Coordinates often in meta tags
- May need multiple regex patterns for features

---

## Debugging Tips

1. **Use tinker to test regex patterns:**
   ```php
   $html = '<div>BAÑOS 3</div>';
   preg_match('/BAÑOS\s*(\d+)/iu', $html, $m);
   dd($m);
   ```

2. **Check character encoding:**
   ```php
   bin2hex('ñ'); // Should be c3b1 for UTF-8
   ```

3. **Test selectors with Playwright MCP:**
   - Use `browser_snapshot` to see page structure
   - Use `browser_evaluate` to test CSS selectors

4. **Validate JSON-LD:**
   - Look for multiple JSON-LD blocks (breadcrumbs, organization, listing)
   - Check for nested structures (`offers.itemOffered`)

---

## ZenRows API Configuration

ZenRows is our web scraping API. Understanding its parameters is critical for successful scraping.

### Core Parameters (Always Sent)

These are automatically included by `ZenRowsClient`:

| Parameter | Value | Purpose |
|-----------|-------|---------|
| `js_render` | `true` | Enables headless browser rendering |
| `premium_proxy` | `true` | Uses residential IPs to bypass anti-bot |
| `js_instructions` | `[{"wait_event": "networkidle"}]` | Waits for page to fully load |
| `block_resources` | `image,font,media` | Speeds up requests |

### Platform-Specific Options via `zenrowsOptions()`

Each config can return additional ZenRows parameters:

```php
public function zenrowsOptions(): array
{
    return [
        'proxy_country' => 'mx',  // Use Mexican IP (geo-restricted sites)
        'wait' => 5000,           // Extra wait time in ms
        'custom_headers' => true, // Enable custom headers
    ];
}
```

### Available ZenRows Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `proxy_country` | string | Two-letter country code (e.g., `mx`, `us`) for geo-restricted content |
| `wait` | integer | Fixed wait time in milliseconds after page load |
| `wait_for` | string | CSS selector to wait for before returning content |
| `custom_headers` | boolean | Enable passing custom HTTP headers |
| `session_id` | integer | Maintain same IP for multiple requests (10 min max) |
| `antibot` | boolean | Enable advanced anti-bot bypass |
| `css_extractor` | string | JSON-encoded CSS selectors for data extraction |
| `autoparse` | boolean | Auto-extract structured data |

### ZenRows Error Codes

| Code | Name | Cause | Solution |
|------|------|-------|----------|
| `RESP001` | Could Not Get Content | Page blocked or geo-restricted | Add `proxy_country`, increase `wait`, add `custom_headers` with Referer |
| `REQS002` | JS Rendering Required | Site needs browser rendering | Already enabled by default |
| `422` | Unprocessable Entity | General failure | Check error body for specific issue |

### Troubleshooting ZenRows Failures

**1. "Bad Configuration" in ZenRows Dashboard**

Check these in order:
1. Add `proxy_country` matching site's target region (e.g., `mx` for Mexican sites)
2. Increase `wait` time if content loads slowly
3. Enable `custom_headers` and add a Referer header

**2. RESP001 "Could Not Get Content"**

```php
// In your config's zenrowsOptions():
return [
    'proxy_country' => 'mx',  // Match site's region
    'wait' => 5000,           // Wait 5 seconds
];
```

**3. Testing ZenRows Directly**

```php
// In tinker - test raw ZenRows response
$apiKey = config('services.zenrows.api_key');
$response = Http::timeout(120)->get('https://api.zenrows.com/v1/', [
    'apikey' => $apiKey,
    'url' => 'https://target-site.com/page',
    'js_render' => 'true',
    'premium_proxy' => 'true',
    'proxy_country' => 'mx',
]);
dd($response->status(), strlen($response->body()));
```

### Platform-Specific ZenRows Requirements

| Platform | `proxy_country` | Notes |
|----------|-----------------|-------|
| Inmuebles24 | - | Works without geo-restriction |
| Vivanuncios | - | Works without geo-restriction |
| Propiedades.com | `mx` | **Required** - geo-restricted to Mexican IPs |

### Best Practices

1. **Always test ZenRows first** - Before writing parser code, verify ZenRows can fetch the page
2. **Check the ZenRows dashboard** - Shows configuration suggestions for failing domains
3. **Start simple** - Try without extra options, add them only if needed
4. **Mexican sites often need `proxy_country=mx`** - Many real estate sites are geo-restricted
5. **Use Playwright for debugging only** - For comparing what ZenRows returns vs browser reality

---

## Platform-Specific Implementation Details

### Propiedades.com

**Geo-Restriction:** Requires `proxy_country=mx` - site returns 403 or different content for non-Mexican IPs.

**Primary Data Source:** `__NEXT_DATA__` JSON (Next.js site)

#### `__NEXT_DATA__` Structure

```
props.pageProps.results
├── property        # Core listing data (price, size, bathrooms, coordinates)
├── seo             # Title and meta info (meta_title is most complete)
├── profile         # Publisher info (name, lastname, picture_url)
├── services        # Amenities as key-value pairs (cistern: "Cisterna")
├── gallery         # Image filenames (need to construct full URL)
├── amenities       # Features (size_ground, parking_num, age)
└── is_truncated    # Boolean - indicates if description is truncated
```

**Key Extraction Priorities:**

| Field | Priority 1 | Priority 2 | Priority 3 |
|-------|------------|------------|------------|
| Title | `seo.meta_title` | CSS extraction | `property.keywords_title` |
| Price | `property.price` | JSON-LD | CSS extraction |
| Publisher | `profile.name + profile.lastname` | CSS extraction | - |
| Coordinates | `property.latitude/longitude` | Meta tags (ICBM) | JSON-LD |
| Amenities | `services` object | JSON-LD amenityFeature | HTML section |
| Images | `gallery` array | CSS extraction | HTML patterns |

**Operation Type Detection:**
- URL is authoritative (`-renta-` = rent, `-venta-` = sale)
- JSON-LD `businessFunction` can be **wrong** (shows "Sell" for rentals)

**URL Patterns:**
- Pagination: `?pagina=N` (preserves existing query params)
- External ID: 8-digit number at end of slug (`-30554556`)
- Format: `https://propiedades.com/inmuebles/{slug}-{external_id}`

**Image URLs:**
- Gallery provides filenames, construct URL: `https://propiedadescom.s3.amazonaws.com/files/1200x507/{filename}`
- Images use UUID patterns: `{uuid}.jpg`
- Use UUID to deduplicate across resolutions

**Known Platform Limitations:**
- **Description is truncated by API** - `is_truncated: true` in response, ~200 chars max
- **Expired listings redirect** - Old URLs redirect to different active listings
- **CSS extracted data can be incomplete** - `__NEXT_DATA__` is more reliable

**Example ZenRows Config:**
```php
public function zenrowsOptions(): array
{
    return [
        'proxy_country' => 'mx',  // Required for geo-restriction
    ];
}
```

---

### Inmuebles24

**Geo-Restriction:** None - works without `proxy_country`

**Primary Data Source:** JSON-LD with some `__NEXT_DATA__` augmentation

**Key Patterns:**
- URL format: `https://www.inmuebles24.com/propiedades/{slug}-{external_id}.html`
- External ID: Numeric ID in URL slug before `.html`
- Pagination: `?pagina=N`
- Images: Full URLs in JSON-LD `image` array

**Operation Type:**
- JSON-LD `businessFunction` is reliable
- Can also detect from URL path segment

---

### Vivanuncios

**Geo-Restriction:** None - works without `proxy_country`

**Primary Data Source:** `window.dataLayer` for analytics data, HTML for content

**Key Patterns:**
- URL format: `https://www.vivanuncios.com.mx/{category}/{slug}-{external_id}`
- External ID: In URL slug
- Pagination: `?page=N`

---

## Common Pitfalls and Lessons Learned

### 1. Don't Trust a Single Data Source
Always implement fallback extraction chains. JSON-LD may have wrong operation types, CSS may return truncated text, and meta tags may be incomplete.

### 2. Validate Data Against URL
The URL often contains reliable operation type and property type info. Use it to correct potentially wrong JSON-LD data (e.g., propiedades.com marking rentals as "Sell").

### 3. Test with Real ZenRows, Not Playwright
Playwright captures what a browser sees locally. ZenRows uses different IPs and may get geo-blocked or receive different content. Always validate with actual ZenRows requests.

### 4. Expired/Redirect Listings
Sites may redirect old listing URLs to other active listings. Your parser will extract data from wherever it lands - this is expected behavior.

### 5. Description Truncation Can Be Platform-Side
If descriptions are consistently short (~200 chars), check for `is_truncated` flags or API limits. This may not be a parser bug.

### 6. Image Deduplication Strategy
- Extract unique identifier (UUID, numeric ID) from image URLs
- Keep highest resolution version when duplicates found
- Filter out non-listing images (related listings use different URL patterns)

### 7. Publisher Name Extraction
Often hidden in nested structures:
- Propiedades: `results.profile.name + results.profile.lastname`
- Inmuebles24: JSON-LD `seller` or CSS extraction
- May require combining multiple fields

### 8. Coordinate Sources (Priority Order)
1. Structured data (`__NEXT_DATA__`, JSON-LD)
2. Meta tags (`ICBM`, `geo.position`)
3. Regex patterns in HTML
4. Always validate bounds (Mexico: 14-33°N, -118 to -86°W)

---

## Single-Request Optimization

### Overview

Previously, scraping a listing required **two ZenRows API calls**:
1. CSS extraction for structured HTML data
2. Raw HTML fetch for JavaScript variable extraction (dataLayer, `__NEXT_DATA__`, etc.)

The single-request optimization reduces this to **one request**, cutting ZenRows costs by 50%.

### How It Works

1. **CSS extraction includes `all_scripts` selector** - Extracts text content of all `<script>` tags
2. **ScraperService builds synthetic HTML** - Reconstructs minimal HTML from extracted scripts
3. **Parsers work unchanged** - They receive the synthetic HTML and extract JS variables via regex as before

### Implementation Details

**Config Changes (each platform's `listingExtractor()`):**
```php
public function listingExtractor(): array
{
    return [
        // ... existing selectors ...

        // Script extraction for single-request optimization
        'all_scripts' => 'script',

        // For Next.js sites only (propiedades.com):
        'next_data' => 'script#__NEXT_DATA__',
    ];
}
```

**ScraperService Changes:**
```php
public function scrapeListing(string $url): array
{
    // Single request with CSS extraction (includes all_scripts)
    $extracted = $this->zenRows->fetchListingPage($url, $config->listingExtractor(), ...);

    // Build synthetic HTML from extracted scripts
    $syntheticHtml = $this->buildSyntheticHtml($extracted);

    return $listingParser->parse($extracted, $syntheticHtml, $url);
}

protected function buildSyntheticHtml(array $extracted): string
{
    $html = '';

    // Add __NEXT_DATA__ if present (Next.js sites)
    if (!empty($extracted['next_data'])) {
        $html .= '<script id="__NEXT_DATA__">' . $extracted['next_data'] . '</script>';
    }

    // Add all scripts (for dataLayer, JSON-LD, JS variables)
    foreach ($extracted['all_scripts'] ?? [] as $script) {
        // Detect and tag JSON-LD scripts
        if (str_starts_with(trim($script), '{') && str_contains($script, '@type')) {
            $html .= '<script type="application/ld+json">' . $script . '</script>';
        } else {
            $html .= '<script>' . $script . '</script>';
        }
    }

    // Add meta tags for coordinates
    if (!empty($extracted['meta_icbm'])) {
        $html .= '<meta name="ICBM" content="' . $extracted['meta_icbm'] . '">';
    }

    return $html;
}
```

### Key Findings During Implementation

1. **`script#__NEXT_DATA__` selector works** - Returns the full JSON content (Next.js sites)
2. **`script[type="application/ld+json"]` does NOT work reliably** - ZenRows returns empty
3. **`script` (all scripts) works** - Returns array of all script contents
4. **JSON-LD must be filtered client-side** - Check for `{` start and `@type` content

### Reverting to Two-Request Approach

If needed, to revert to the original two-request approach:

1. **Remove from configs:** Delete `all_scripts` and `next_data` from `listingExtractor()`

2. **Restore ScraperService:**
```php
public function scrapeListing(string $url): array
{
    $extracted = $this->zenRows->fetchListingPage($url, ...);

    // Second request for raw HTML
    $rawHtml = $this->zenRows->fetchRawHtml($url, $config->zenrowsOptions());

    return $listingParser->parse($extracted, $rawHtml, $url);
}
```

3. **Remove `buildSyntheticHtml()` method**

---

## Platform Seeder Requirement

When adding a new scraper, you MUST add the platform to the seeder.

**Location:** `database/seeders/PlatformSeeder.php`

```php
$platforms = [
    // ... existing platforms ...
    [
        'name' => 'newplatform',
        'slug' => 'newplatform',
        'base_url' => 'https://www.newplatform.com',
    ],
];
```

**Run the seeder:**
```bash
php artisan db:seed --class=PlatformSeeder
```

Without this, `ScraperFactory::detectPlatformFromUrl()` will throw "Platform not found" errors.

---

## New Scraper Checklist

When implementing a new scraper:

- [ ] Create `{Platform}Config.php` with all required methods
- [ ] Create `{Platform}SearchParser.php`
- [ ] Create `{Platform}ListingParser.php`
- [ ] Register in `ScraperFactory.php` (4 methods: createConfig, createSearchParser, createListingParser, detectPlatformFromUrl)
- [ ] Add platform to `PlatformSeeder.php`
- [ ] Run `php artisan db:seed --class=PlatformSeeder`
- [ ] Add unit tests for search and listing parsers
- [ ] Test with real ZenRows requests (not just mocked data)
- [ ] Document any platform-specific quirks in this file
- [ ] Verify `zenrowsOptions()` includes necessary parameters (e.g., `proxy_country` for geo-restricted sites)
