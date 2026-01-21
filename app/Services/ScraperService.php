<?php

namespace App\Services;

use App\Services\Scrapers\ScraperFactory;
use Illuminate\Support\Facades\Log;

class ScraperService
{
    public function __construct(
        protected ZenRowsClient $zenRows,
        protected ScraperFactory $factory,
    ) {}

    /**
     * Discover listings from a search page.
     *
     * @return array{total_results: int|null, total_pages: int|null, listings: array<array{url: string, external_id: string|null, preview: array}>}
     *
     * @throws \RuntimeException
     */
    public function discoverPage(string $url, int $page = 1): array
    {
        $platform = $this->factory->detectPlatformFromUrl($url);
        $config = $this->factory->createConfig($platform);
        $searchParser = $this->factory->createSearchParser($platform, $config);

        $paginatedUrl = $page > 1 ? $config->paginateUrl($url, $page) : $url;

        Log::debug('ScraperService: discovering page', [
            'url' => $paginatedUrl,
            'page' => $page,
            'platform' => $platform->slug,
        ]);

        $extracted = $this->zenRows->fetchSearchPage(
            $paginatedUrl,
            $config->searchExtractor(),
            $config->zenrowsOptions()
        );

        return $searchParser->parse($extracted, $this->getBaseUrl($url));
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
        $platform = $this->factory->detectPlatformFromUrl($url);
        $config = $this->factory->createConfig($platform);
        $listingParser = $this->factory->createListingParser($platform, $config);
        $zenrowsOptions = $config->zenrowsOptions();

        Log::debug('ScraperService: scraping listing', [
            'url' => $url,
            'platform' => $platform->slug,
        ]);

        // Two requests: CSS extraction for structured data, raw HTML for JS variables
        $extracted = $this->zenRows->fetchListingPage(
            $url,
            $config->listingExtractor(),
            $zenrowsOptions
        );

        // Second request for JS variables - handle failure gracefully
        $rawHtml = '';
        try {
            $rawHtml = $this->zenRows->fetchRawHtml($url, $zenrowsOptions);
            Log::debug('ScraperService: got raw HTML', [
                'url' => $url,
                'length' => strlen($rawHtml),
                'has_description' => str_contains($rawHtml, 'longDescription') || str_contains($rawHtml, 'description'),
            ]);
        } catch (\RuntimeException $e) {
            Log::error('ScraperService: failed to fetch raw HTML - images and description may be incomplete', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            // Continue with empty rawHtml - parser will work with CSS-extracted data only
        }

        return $listingParser->parse($extracted, $rawHtml, $url);
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
