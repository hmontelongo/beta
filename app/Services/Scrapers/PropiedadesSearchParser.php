<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperConfigInterface;
use App\Contracts\SearchParserInterface;

class PropiedadesSearchParser implements SearchParserInterface
{
    public function __construct(protected ScraperConfigInterface $config) {}

    /**
     * Parse search results from ZenRows CSS-extracted data.
     *
     * @param  array<string, mixed>  $extracted  Data from ZenRows css_extractor
     * @param  string  $baseUrl  Base URL for resolving relative links
     * @return array{total_results: int|null, total_pages: int|null, listings: array<array{url: string, external_id: string|null, preview: array}>}
     */
    public function parse(array $extracted, string $baseUrl): array
    {
        $listings = [];
        $seenUrls = [];

        // Get arrays - ZenRows returns arrays when multiple elements match
        $urls = $this->toArray($extracted['urls'] ?? []);
        $titles = $this->toArray($extracted['titles'] ?? []);
        $prices = $this->toArray($extracted['prices'] ?? []);
        $locations = $this->toArray($extracted['locations'] ?? []);
        $images = $this->toArray($extracted['images'] ?? []);

        foreach ($urls as $i => $url) {
            if (empty($url)) {
                continue;
            }

            // Skip non-listing URLs (navigation, filters, etc.)
            if (! str_contains($url, '/inmuebles/')) {
                continue;
            }

            $fullUrl = $this->resolveUrl($url, $baseUrl);

            // Remove URL hash and query params for cleaner URLs
            $cleanUrl = $this->cleanListingUrl($fullUrl);

            // Dedupe by URL
            if (isset($seenUrls[$cleanUrl])) {
                continue;
            }
            $seenUrls[$cleanUrl] = true;

            $listings[] = [
                'url' => $cleanUrl,
                'external_id' => $this->extractExternalId($cleanUrl),
                'preview' => [
                    'title' => $this->cleanTitle($titles[$i] ?? null),
                    'price' => $this->cleanPrice($prices[$i] ?? null),
                    'location' => $this->cleanText($locations[$i] ?? null),
                    'image' => $this->cleanImageUrl($images[$i] ?? null),
                ],
            ];
        }

        // Parse total results from H1 or page title
        // Format: "2,163 Casas en Renta en Jalisco"
        $h1Title = $extracted['h1_title'] ?? '';
        if (is_array($h1Title)) {
            $h1Title = $h1Title[0] ?? '';
        }

        $pageTitle = $extracted['page_title'] ?? '';
        if (is_array($pageTitle)) {
            $pageTitle = $pageTitle[0] ?? '';
        }

        $totalResults = $this->parseTotalResults($h1Title) ?: $this->parseTotalResults($pageTitle);

        // Parse total pages from pagination
        $paginationLinks = $this->toArray($extracted['pagination_links'] ?? []);
        $paginationNumbers = $this->toArray($extracted['pagination_numbers'] ?? []);
        $totalPages = $this->parseTotalPages($paginationLinks, $paginationNumbers, $totalResults);

        return [
            'total_results' => $totalResults,
            'total_pages' => $totalPages,
            'listings' => $listings,
        ];
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
     * Resolve a potentially relative URL.
     */
    protected function resolveUrl(string $url, string $baseUrl): string
    {
        // Already absolute
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        // Relative URL
        if (str_starts_with($url, '/')) {
            $parsedBase = parse_url($baseUrl);

            return ($parsedBase['scheme'] ?? 'https').'://'.($parsedBase['host'] ?? 'propiedades.com').$url;
        }

        return $url;
    }

    /**
     * Clean listing URL by removing hash fragments and unnecessary query params.
     */
    protected function cleanListingUrl(string $url): string
    {
        // Remove hash fragment (e.g., #tipos=casas-renta&area=jalisco&pos=1)
        $url = explode('#', $url)[0];

        // Remove query string for cleaner URLs
        $url = explode('?', $url)[0];

        return $url;
    }

    /**
     * Extract external ID from URL.
     */
    protected function extractExternalId(string $url): ?string
    {
        return $this->config->extractExternalId($url);
    }

    /**
     * Parse total results from title text.
     * Example: "2,163 Casas en Renta en Jalisco"
     */
    protected function parseTotalResults(string $title): ?int
    {
        // Match number at beginning, with optional thousands separators
        if (preg_match('/^([\d,\.]+)\s+/u', trim($title), $matches)) {
            // Remove thousands separators and convert
            $number = preg_replace('/[,\.]/', '', $matches[1]);

            return (int) $number;
        }

        return null;
    }

    /**
     * Parse total pages from pagination links and numbers.
     *
     * @param  array<string>  $paginationLinks  Pagination link URLs
     * @param  array<string>  $paginationNumbers  Pagination number texts
     */
    protected function parseTotalPages(array $paginationLinks, array $paginationNumbers, ?int $totalResults): int
    {
        $maxPage = 1;

        // Parse page numbers from pagination links (?pagina=N)
        foreach ($paginationLinks as $link) {
            if (preg_match('/[?&]pagina=(\d+)/', $link, $matches)) {
                $pageNum = (int) $matches[1];
                if ($pageNum > $maxPage) {
                    $maxPage = $pageNum;
                }
            }
        }

        // Parse from pagination number elements
        foreach ($paginationNumbers as $number) {
            if (is_numeric(trim($number))) {
                $pageNum = (int) trim($number);
                if ($pageNum > $maxPage) {
                    $maxPage = $pageNum;
                }
            }
        }

        // Calculate from total results (propiedades.com shows ~48 per page based on initial analysis)
        if ($totalResults !== null && $totalResults > 0) {
            $calculated = (int) ceil($totalResults / 48);
            if ($calculated > $maxPage) {
                $maxPage = $calculated;
            }
        }

        return $maxPage;
    }

    /**
     * Clean title text (remove ID suffix).
     */
    protected function cleanTitle(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Remove ID suffix like ", ID: 30554556"
        $text = preg_replace('/,?\s*ID:\s*\d+$/i', '', $text);

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text ?: null;
    }

    /**
     * Clean price text.
     */
    protected function cleanPrice(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text ?: null;
    }

    /**
     * Clean extracted text.
     */
    protected function cleanText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text ?: null;
    }

    /**
     * Clean and upgrade image URL.
     */
    protected function cleanImageUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        // Upgrade to higher resolution if possible
        // propiedades.com uses pattern: /files/{size}/filename.jpeg
        // Available sizes: 600x400, 1200x507, 336x200
        $url = preg_replace('/\/files\/\d+x\d+\//', '/files/600x400/', $url);

        return $url;
    }
}
