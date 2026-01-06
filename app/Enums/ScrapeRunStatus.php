<?php

namespace App\Enums;

enum ScrapeRunStatus: string
{
    case Pending = 'pending';
    case Discovering = 'discovering';
    case Scraping = 'scraping';
    case Completed = 'completed';
    case Failed = 'failed';
}
