<?php

namespace App\Contracts;

interface ScraperConfigInterface
{
    /**
     * CSS extractor configuration for search pages.
     *
     * @return array<string, string>
     */
    public function searchExtractor(): array;

    /**
     * CSS extractor configuration for listing pages.
     *
     * @return array<string, string>
     */
    public function listingExtractor(): array;

    /**
     * Regex patterns for extracting JavaScript variables.
     *
     * @return array<string, string>
     */
    public function jsPatterns(): array;

    /**
     * Regex patterns for extracting dataLayer variables.
     *
     * @return array<string, string>
     */
    public function dataLayerPatterns(): array;

    /**
     * Property type mappings (platform ID => standard type).
     *
     * @return array<int|string, string>
     */
    public function propertyTypes(): array;

    /**
     * Operation type mappings (platform ID => standard type).
     *
     * @return array<int, string>
     */
    public function operationTypes(): array;

    /**
     * Currency type mappings (platform ID => standard code).
     *
     * @return array<int, string>
     */
    public function currencyTypes(): array;

    /**
     * Generate paginated URL from base URL and page number.
     */
    public function paginateUrl(string $baseUrl, int $page): string;

    /**
     * Extract external ID from a listing URL.
     */
    public function extractExternalId(string $url): ?string;

    /**
     * CSS selector to wait for on search pages before extracting data.
     */
    public function searchWaitFor(): string;

    /**
     * CSS selector to wait for on listing pages before extracting data.
     */
    public function listingWaitFor(): string;
}
