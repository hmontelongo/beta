<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperConfigInterface;
use App\Contracts\SearchParserInterface;

class VivanunciosSearchParser implements SearchParserInterface
{
    public function __construct(protected ScraperConfigInterface $config) {}

    /**
     * Parse search results from ZenRows CSS-extracted data.
     *
     * @param  array<string, mixed>  $extracted  Data from ZenRows css_extractor
     * @param  string  $baseUrl  Base URL for resolving relative links
     * @return array{total_results: int, visible_pages: array<int>, listings: array<array{url: string, external_id: string|null, preview: array}>}
     */
    public function parse(array $extracted, string $baseUrl): array
    {
        $listings = [];
        $seenUrls = [];

        // Get arrays - ZenRows returns arrays when multiple elements match
        $urls = $this->toArray($extracted['urls'] ?? []);
        $externalIds = $this->toArray($extracted['external_ids'] ?? []);
        $titles = $this->toArray($extracted['titles'] ?? []);
        $prices = $this->toArray($extracted['prices'] ?? []);
        $locations = $this->toArray($extracted['locations'] ?? []);
        $images = $this->toArray($extracted['images'] ?? []);

        foreach ($urls as $i => $url) {
            if (empty($url)) {
                continue;
            }

            $fullUrl = $this->resolveUrl($url, $baseUrl);

            // Dedupe by URL
            if (isset($seenUrls[$fullUrl])) {
                continue;
            }
            $seenUrls[$fullUrl] = true;

            // Get external ID from data-id or extract from URL
            $externalId = $externalIds[$i] ?? $this->config->extractExternalId($fullUrl);

            $listings[] = [
                'url' => $fullUrl,
                'external_id' => $externalId,
                'preview' => [
                    'title' => $this->cleanText($titles[$i] ?? null),
                    'price' => $this->cleanText($prices[$i] ?? null),
                    'location' => $this->cleanText($locations[$i] ?? null),
                    'image' => $this->cleanImageUrl($images[$i] ?? null),
                ],
            ];
        }

        // Parse pagination from page title (may be array from ZenRows)
        $pageTitle = $extracted['page_title'] ?? '';
        if (is_array($pageTitle)) {
            $pageTitle = $pageTitle[0] ?? '';
        }
        $totalResults = $this->parseTotalResults($pageTitle);
        $visiblePages = $this->parseVisiblePages($this->toArray($extracted['page_links'] ?? []));

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

        if (is_string($value) && ! empty($value)) {
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
            return explode('?', $url)[0]; // Remove query params
        }

        // Relative URL
        if (str_starts_with($url, '/')) {
            return rtrim($baseUrl, '/').explode('?', $url)[0];
        }

        return $baseUrl.'/'.explode('?', $url)[0];
    }

    /**
     * Parse total results from page title.
     * Example: "60 Propiedades en renta en Tonalá" or "120 Departamentos en renta"
     */
    protected function parseTotalResults(string $pageTitle): int
    {
        if (preg_match('/^([\d,\.]+)\s+(propiedades?|departamentos?|inmuebles?|casas?)/iu', $pageTitle, $matches)) {
            return (int) preg_replace('/[,\.]/', '', $matches[1]);
        }

        return 0;
    }

    /**
     * Extract visible page numbers from pagination UI.
     * Returns array of page numbers that are clickable/visible.
     *
     * @param  array<string>  $pageLinks  Array of data-qa values like "PAGING_1", "PAGING_2"
     * @return array<int>
     */
    protected function parseVisiblePages(array $pageLinks): array
    {
        $pages = [];

        // Parse page numbers from data-qa attributes
        foreach ($pageLinks as $link) {
            if (preg_match('/PAGING_(\d+)/', $link, $matches)) {
                $pages[] = (int) $matches[1];
            }
        }

        // Return unique sorted pages
        $pages = array_unique($pages);
        sort($pages);

        return $pages;
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

        // Upgrade to higher resolution if possible (same CDN as Inmuebles24)
        $url = str_replace(['360x266', '720x532'], '1200x1200', $url);

        return $url;
    }
}
