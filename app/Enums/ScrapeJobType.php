<?php

namespace App\Enums;

enum ScrapeJobType: string
{
    case Discovery = 'discovery';
    case Listing = 'listing';
}
