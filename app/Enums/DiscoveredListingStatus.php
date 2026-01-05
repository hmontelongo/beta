<?php

namespace App\Enums;

enum DiscoveredListingStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Scraped = 'scraped';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
