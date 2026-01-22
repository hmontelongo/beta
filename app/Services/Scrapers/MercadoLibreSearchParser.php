<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperConfigInterface;
use App\Contracts\SearchParserInterface;

class MercadoLibreSearchParser implements SearchParserInterface
{
    public function __construct(protected ScraperConfigInterface $config) {}

    /**
     * Parse search results from ZenRows CSS-extracted data.
     *
     * @param  array<string, mixed>  $extracted  Data from ZenRows css_extractor
     * @param  string  $baseUrl  Base URL for resolving relative links
     * @return array{total_results: int|null, visible_pages: array<int>, listings: array<array{url: string, external_id: string|null, preview: array}>}
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

            // Skip non-listing URLs (must contain MLM pattern with digits)
            if (! preg_match('/MLM-?\d+/', $url)) {
                continue;
            }

            $fullUrl = $this->resolveUrl($url, $baseUrl);
            $cleanUrl = $this->cleanListingUrl($fullUrl);

            // Dedupe by URL
            if (isset($seenUrls[$cleanUrl])) {
                continue;
            }
            $seenUrls[$cleanUrl] = true;

            // Map to the correct index
            $listingIndex = count($listings);

            $listings[] = [
                'url' => $cleanUrl,
                'external_id' => $this->extractExternalId($cleanUrl),
                'preview' => [
                    'title' => $this->cleanText($titles[$i] ?? $titles[$listingIndex] ?? null),
                    'price' => $this->cleanPrice($prices[$i] ?? $prices[$listingIndex] ?? null),
                    'location' => $this->cleanText($locations[$i] ?? $locations[$listingIndex] ?? null),
                    'image' => $images[$i] ?? $images[$listingIndex] ?? null,
                ],
            ];
        }

        // Parse total results from H1 title
        // Format: "49 Casas en Renta en Zapopan, Jalisco"
        $h1Title = $extracted['h1_title'] ?? '';
        if (is_array($h1Title)) {
            $h1Title = $h1Title[0] ?? '';
        }

        $totalResults = $this->parseTotalResults($h1Title);

        // Parse visible pages from results count
        // MercadoLibre uses offset pagination, calculate pages from total
        $visiblePages = $this->calculateVisiblePages($totalResults);

        return [
            'total_results' => $totalResults,
            'visible_pages' => $visiblePages,
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

            return ($parsedBase['scheme'] ?? 'https').'://'.($parsedBase['host'] ?? 'inmuebles.mercadolibre.com.mx').$url;
        }

        return $url;
    }

    /**
     * Clean listing URL by removing tracking fragments and parameters.
     */
    protected function cleanListingUrl(string $url): string
    {
        // Remove hash fragment (tracking info)
        $url = explode('#', $url)[0];

        // Remove tracking query parameters but keep essential ones
        $parsed = parse_url($url);
        $cleanUrl = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '').($parsed['path'] ?? '');

        return $cleanUrl;
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
     * Example: "49 Casas en Renta en Zapopan, Jalisco"
     */
    protected function parseTotalResults(string $title): ?int
    {
        // Clean up multi-line and whitespace
        $title = preg_replace('/\s+/', ' ', trim($title));

        // Match number at beginning
        if (preg_match('/^([\d,\.]+)\s*/u', $title, $matches)) {
            $number = preg_replace('/[,\.]/', '', $matches[1]);

            return (int) $number;
        }

        return null;
    }

    /**
     * Calculate visible page numbers based on total results.
     * MercadoLibre uses 48 items per page.
     *
     * @return array<int>
     */
    protected function calculateVisiblePages(?int $totalResults): array
    {
        if ($totalResults === null || $totalResults <= 0) {
            return [];
        }

        // 48 items per page
        $totalPages = (int) ceil($totalResults / 48);

        // Cap at reasonable limit
        return range(1, min($totalPages, 100));
    }

    /**
     * Clean extracted text.
     */
    protected function cleanText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

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

        // Extract price value (MercadoLibre format: "$ 65,000" or "$65,000/mes")
        if (preg_match('/\$\s*[\d,]+(?:\s*\/\s*mes)?/iu', $text, $match)) {
            return preg_replace('/\s+/', ' ', trim($match[0]));
        }

        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text ?: null;
    }
}
