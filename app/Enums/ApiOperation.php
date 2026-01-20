<?php

namespace App\Enums;

enum ApiOperation: string
{
    case PropertyCreation = 'property_creation';
    case SearchScrape = 'search_scrape';
    case ListingScrape = 'listing_scrape';
    case RawHtmlFetch = 'raw_html_fetch';

    public function label(): string
    {
        return match ($this) {
            self::PropertyCreation => 'Property Creation',
            self::SearchScrape => 'Search Page Scrape',
            self::ListingScrape => 'Listing Scrape',
            self::RawHtmlFetch => 'Raw HTML Fetch',
        };
    }
}
