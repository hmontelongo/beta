<?php

namespace App\Enums;

enum ScrapePhase: string
{
    case Discover = 'discover';
    case Scrape = 'scrape';
}
