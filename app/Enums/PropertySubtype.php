<?php

namespace App\Enums;

enum PropertySubtype: string
{
    case Penthouse = 'penthouse';
    case Studio = 'studio';
    case Loft = 'loft';
    case Duplex = 'duplex';
    case Townhouse = 'townhouse';
    case Villa = 'villa';
    case Other = 'other';
}
