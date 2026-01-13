<?php

namespace App\Contracts;

interface SearchParserInterface
{
    /**
     * Parse search results from extracted data.
     *
     * @param  array<string, mixed>  $extracted  Data extracted via CSS selectors
     * @param  string  $baseUrl  The original search URL
     * @return array{total_results: int|null, total_pages: int|null, listings: array<array{url: string, external_id: string|null, preview: array}>}
     */
    public function parse(array $extracted, string $baseUrl): array;
}
