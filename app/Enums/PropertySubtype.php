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

    /**
     * Get Spanish label for the property subtype.
     */
    public function labelEs(): string
    {
        return match ($this) {
            self::Penthouse => 'Penthouse',
            self::Studio => 'Estudio',
            self::Loft => 'Loft',
            self::Duplex => 'Dúplex',
            self::Triplex => 'Tríplex',
            self::GroundFloor => 'Planta baja',
            self::Townhouse => 'Casa adosada',
            self::Villa => 'Villa',
            self::Other => 'Otro',
        };
    }
}
