<?php

namespace App\Enums;

enum PropertySubtype: string
{
    case Penthouse = 'penthouse';
    case Studio = 'studio';
    case Loft = 'loft';
    case Duplex = 'duplex';
    case Triplex = 'triplex';
    case GroundFloor = 'ground_floor';
    case Townhouse = 'townhouse';
    case Villa = 'villa';
    case Other = 'other';
}
