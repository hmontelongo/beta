<?php

namespace App\Services;

use App\Services\Scrapers\Inmuebles24Config;
use App\Services\Scrapers\Inmuebles24ListingParser;
use App\Services\Scrapers\Inmuebles24SearchParser;
use Illuminate\Support\Facades\Log;

class ScraperService
{
    public function __construct(
        protected ZenRowsClient $zenRows,
        protected Inmuebles24Config $config,
        protected Inmuebles24SearchParser $searchParser,
        protected Inmuebles24ListingParser $listingParser,
    ) {}

    /**
     * Discover listings from a search page.
     *
     * @return array{total_results: int, total_pages: int, listings: array<array{url: string, external_id: string|null, preview: array}>}
     *
     * @throws \RuntimeException
     */
    public function discoverPage(string $url, int $page = 1): array
    {
        $paginatedUrl = $this->addPagination($url, $page);

        Log::debug('ScraperService: discovering page', ['url' => $paginatedUrl, 'page' => $page]);

        $extracted = $this->zenRows->fetchSearchPage(
            $paginatedUrl,
            $this->config->searchExtractor()
        );

        return $this->searchParser->parse($extracted, $this->getBaseUrl($url));
    }

    /**
     * Scrape a single listing page.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function scrapeListing(string $url): array
    {
        Log::debug('ScraperService: scraping listing', ['url' => $url]);

        // Two requests: CSS extraction for structured data, raw HTML for JS variables
        $extracted = $this->zenRows->fetchListingPage(
            $url,
            $this->config->listingExtractor()
        );

        $rawHtml = $this->zenRows->fetchRawHtml($url);

        return $this->listingParser->parse($extracted, $rawHtml, $url);
    }

    /**
     * Add pagination parameter to URL.
     */
    protected function addPagination(string $url, int $page): string
    {
        if ($page <= 1) {
            return $url;
        }

        // Inmuebles24 uses -pagina-N suffix before the .html extension
        // Example: /departamentos-renta-jalisco.html -> /departamentos-renta-jalisco-pagina-2.html
        if (str_contains($url, '.html')) {
            return str_replace('.html', "-pagina-{$page}.html", $url);
        }

        // Fallback: append as query parameter
        $separator = str_contains($url, '?') ? '&' : '?';

        return "{$url}{$separator}pagina={$page}";
    }

    /**
     * Extract base URL from a full URL.
     */
    protected function getBaseUrl(string $url): string
    {
        $parsed = parse_url($url);

        return "{$parsed['scheme']}://{$parsed['host']}";
    }
}
