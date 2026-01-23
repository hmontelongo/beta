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

    /**
     * Get Spanish label for the property type.
     */
    public function labelEs(): string
    {
        return match ($this) {
            self::House => 'Casa',
            self::Apartment => 'Departamento',
            self::Office => 'Oficina',
            self::Commercial => 'Local comercial',
            self::Land => 'Terreno',
            self::Warehouse => 'Bodega',
            self::Building => 'Edificio',
            self::Hotel => 'Hotel',
            self::Ranch => 'Rancho',
            self::Industrial => 'Nave industrial',
            self::Parking => 'Estacionamiento',
            self::Room => 'Cuarto',
        };
    }
}
