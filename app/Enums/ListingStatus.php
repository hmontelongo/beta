<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Active = 'active';
    case Delisted = 'delisted';
    case Error = 'error';
}
