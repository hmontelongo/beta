<?php

namespace App\Enums;

enum PropertySourceType: string
{
    case Scraped = 'scraped';
    case Native = 'native';

    public function labelEs(): string
    {
        return match ($this) {
            self::Scraped => 'Agregada',
            self::Native => 'Propia',
        };
    }
}
