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
     * Uses single-request optimization: CSS extraction includes 'all_scripts' selector,
     * which captures all script tag contents. We reconstruct synthetic HTML from these
     * scripts for parsers that need to extract JS variables (dataLayer, __NEXT_DATA__, etc.)
     *
     * This reduces ZenRows API costs by 50% compared to the previous two-request approach.
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

        Log::debug('ScraperService: scraping listing', [
            'url' => $url,
            'platform' => $platform->slug,
        ]);

        // Single request: CSS extraction includes 'all_scripts' to capture JS variables
        $extracted = $this->zenRows->fetchListingPage(
            $url,
            $config->listingExtractor(),
            $config->zenrowsOptions()
        );

        // Build synthetic HTML from extracted scripts for parser compatibility
        $syntheticHtml = $this->buildSyntheticHtml($extracted);

        Log::debug('ScraperService: built synthetic HTML from scripts', [
            'url' => $url,
            'synthetic_length' => strlen($syntheticHtml),
            'scripts_count' => count($extracted['all_scripts'] ?? []),
        ]);

        return $listingParser->parse($extracted, $syntheticHtml, $url);
    }

    /**
     * Build synthetic HTML from extracted script contents.
     *
     * Parsers expect raw HTML to extract JS variables (dataLayer, __NEXT_DATA__, etc.)
     * via regex patterns. This method reconstructs minimal HTML containing the scripts
     * so existing parser logic works without modification.
     *
     * @param  array<string, mixed>  $extracted  Data from ZenRows CSS extraction
     */
    protected function buildSyntheticHtml(array $extracted): string
    {
        $html = '';

        // Add __NEXT_DATA__ if present (Next.js sites like propiedades.com)
        if (! empty($extracted['next_data'])) {
            $html .= '<script id="__NEXT_DATA__" type="application/json">'.$extracted['next_data'].'</script>';
        }

        // Add all scripts (for dataLayer, JS variables, JSON-LD extraction)
        $allScripts = $extracted['all_scripts'] ?? [];
        foreach ($allScripts as $script) {
            $trimmed = trim($script);
            if (empty($trimmed)) {
                continue;
            }

            // Check if this is JSON-LD (starts with { and contains @type)
            if (str_starts_with($trimmed, '{') && str_contains($trimmed, '@type')) {
                $html .= '<script type="application/ld+json">'.$trimmed.'</script>';
            } else {
                $html .= '<script>'.$trimmed.'</script>';
            }
        }

        // Add meta tags if extracted (for coordinate extraction)
        if (! empty($extracted['meta_icbm'])) {
            $html .= '<meta name="ICBM" content="'.$extracted['meta_icbm'].'">';
        }
        if (! empty($extracted['meta_geo_position'])) {
            $html .= '<meta name="geo.position" content="'.$extracted['meta_geo_position'].'">';
        }

        return $html;
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
