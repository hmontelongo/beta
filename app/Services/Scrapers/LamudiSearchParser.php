<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperConfigInterface;
use App\Contracts\SearchParserInterface;

class LamudiSearchParser implements SearchParserInterface
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

        // Filter titles to only listing-relevant ones (remove filter/navigation titles)
        $titles = $this->filterListingTitles($titles);

        foreach ($urls as $i => $url) {
            if (empty($url)) {
                continue;
            }

            // Skip non-listing URLs
            if (! str_contains($url, '/detalle/')) {
                continue;
            }

            $fullUrl = $this->resolveUrl($url, $baseUrl);
            $cleanUrl = $this->cleanListingUrl($fullUrl);

            // Dedupe by URL
            if (isset($seenUrls[$cleanUrl])) {
                continue;
            }
            $seenUrls[$cleanUrl] = true;

            // Map to the correct title index (URLs have duplicates, titles don't)
            $titleIndex = count($listings);

            $listings[] = [
                'url' => $cleanUrl,
                'external_id' => $this->extractExternalId($cleanUrl),
                'preview' => [
                    'title' => $this->cleanText($titles[$titleIndex] ?? null),
                    'price' => $this->cleanPrice($prices[$titleIndex] ?? null),
                    'location' => $this->cleanText($locations[$titleIndex] ?? null),
                    'image' => $images[$titleIndex] ?? null,
                ],
            ];
        }

        // Parse total results from H1 title
        // Format: "38 Inmuebles en Renta en Ciudad Granja, Zapopan"
        $h1Title = $extracted['h1_title'] ?? '';
        if (is_array($h1Title)) {
            $h1Title = $h1Title[0] ?? '';
        }

        $totalResults = $this->parseTotalResults($h1Title);

        // Parse visible pages from pagination text
        // Format: "Página 1 de 40"
        $paginationText = $this->toArray($extracted['pagination_text'] ?? []);
        $visiblePages = $this->parseVisiblePages($paginationText);

        return [
            'total_results' => $totalResults,
            'visible_pages' => $visiblePages,
            'listings' => $listings,
        ];
    }

    /**
     * Filter titles to only include listing-relevant ones.
     * Removes navigation, filter, and UI-related titles.
     *
     * @param  array<string>  $titles
     * @return array<string>
     */
    protected function filterListingTitles(array $titles): array
    {
        $listingTitles = [];

        foreach ($titles as $title) {
            $title = trim($title);

            // Skip empty titles
            if (empty($title)) {
                continue;
            }

            // Skip filter/navigation titles
            if (preg_match('/^(RECÁMARAS|BAÑOS|Superficies|Desde|Hasta|CARACTERÍSTICAS|Seguridad|Amenidades|Exterior|Servicios|Ambientes|Qué encontrarás|Puntos de interés|Dile qué necesitas|Más información|Ver mapa|Volver)/iu', $title)) {
                continue;
            }

            // Skip if it's just a number (pagination)
            if (is_numeric($title)) {
                continue;
            }

            // Include listing titles (contain property type + location pattern)
            if (preg_match('/(Casa|Departamento|Terreno|Local|Oficina|Bodega|Nave|Estudio|Lote|Tienda)\s+(en\s+)?(Renta|Venta)/iu', $title)) {
                $listingTitles[] = $title;
            }
        }

        return array_values($listingTitles);
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

            return ($parsedBase['scheme'] ?? 'https').'://'.($parsedBase['host'] ?? 'www.lamudi.com.mx').$url;
        }

        return $url;
    }

    /**
     * Clean listing URL by removing hash fragments.
     */
    protected function cleanListingUrl(string $url): string
    {
        // Remove hash fragment
        return explode('#', $url)[0];
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
     * Example: "38 Inmuebles en Renta en Ciudad Granja, Zapopan"
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
     * Extract visible page numbers from pagination text.
     * Parses "Página X de Y" format to extract total pages.
     *
     * @param  array<string>  $paginationText
     * @return array<int>
     */
    protected function parseVisiblePages(array $paginationText): array
    {
        $totalPages = null;

        foreach ($paginationText as $text) {
            // Match "Página X de Y" pattern
            if (preg_match('/Página\s+(\d+)\s+de\s+(\d+)/iu', $text, $matches)) {
                $totalPages = (int) $matches[2];
                break;
            }
        }

        // Return range of pages (1 to total)
        if ($totalPages !== null && $totalPages > 0) {
            return range(1, min($totalPages, 100)); // Cap at 100 pages
        }

        return [];
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

        // Extract the price value (e.g., "$ 155,000 MXN /mes")
        if (preg_match('/\$\s*[\d,]+\s*(?:MXN|USD)?(?:\s*\/\s*mes)?/iu', $text, $match)) {
            // Normalize whitespace in the matched price
            return preg_replace('/\s+/', ' ', trim($match[0]));
        }

        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text ?: null;
    }
}
