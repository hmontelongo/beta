<?php

namespace App\Contracts;

interface ListingParserInterface
{
    /**
     * Parse a listing page from extracted data and raw HTML.
     *
     * @param  array<string, mixed>  $extracted  Data extracted via CSS selectors
     * @param  string  $rawHtml  The raw HTML of the page
     * @param  string  $url  The original listing URL
     * @return array<string, mixed> Parsed listing data
     */
    public function parse(array $extracted, string $rawHtml, string $url): array;
}
