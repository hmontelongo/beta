<?php

namespace App\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Apartment = 'apartment';
    case Office = 'office';
    case Commercial = 'commercial';
    case Land = 'land';
    case Warehouse = 'warehouse';
    case Building = 'building';
    case Hotel = 'hotel';
    case Ranch = 'ranch';
    case Industrial = 'industrial';
    case Parking = 'parking';
    case Room = 'room';
}
