<?php

namespace App\Enums;

enum ApiService: string
{
    case Claude = 'claude';
    case ZenRows = 'zenrows';

    public function label(): string
    {
        return match ($this) {
            self::Claude => 'Claude AI',
            self::ZenRows => 'ZenRows',
        };
    }
}
